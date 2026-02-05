/*
 * Licensed to the Apache Software Foundation (ASF) under one
 * or more contributor license agreements. See the NOTICE file
 * distributed with this work for additional information
 * regarding copyright ownership. The ASF licenses this file
 * to you under the Apache License, Version 2.0 (the
 * "License"); you may not use this file except in compliance
 * with the License. You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied. See the License for the
 * specific language governing permissions and limitations
 * under the License.
 */

package org.apache.thrift.transport;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertNotNull;
import static org.junit.jupiter.api.Assertions.assertNull;
import static org.junit.jupiter.api.Assertions.assertThrows;
import static org.junit.jupiter.api.Assertions.assertTrue;
import static org.junit.jupiter.api.Assertions.fail;

import java.io.IOException;
import java.nio.charset.StandardCharsets;
import java.util.HashMap;
import java.util.Map;
import javax.security.auth.callback.Callback;
import javax.security.auth.callback.CallbackHandler;
import javax.security.auth.callback.NameCallback;
import javax.security.auth.callback.PasswordCallback;
import javax.security.auth.callback.UnsupportedCallbackException;
import javax.security.sasl.AuthorizeCallback;
import javax.security.sasl.RealmCallback;
import javax.security.sasl.Sasl;
import javax.security.sasl.SaslClient;
import javax.security.sasl.SaslClientFactory;
import javax.security.sasl.SaslException;
import javax.security.sasl.SaslServer;
import javax.security.sasl.SaslServerFactory;
import org.apache.thrift.TConfiguration;
import org.apache.thrift.TProcessor;
import org.apache.thrift.protocol.TProtocolFactory;
import org.apache.thrift.server.ServerTestBase;
import org.apache.thrift.server.TServer;
import org.apache.thrift.server.TServer.Args;
import org.apache.thrift.server.TSimpleServer;
import org.jspecify.annotations.Nullable;
import org.junit.jupiter.api.Test;
import org.slf4j.Logger;
import org.slf4j.LoggerFactory;

public class TestTSaslTransports {

  private static final Logger LOGGER = LoggerFactory.getLogger(TestTSaslTransports.class);

  public static final String HOST = "localhost";
  public static final String SERVICE = "thrift-test";
  public static final String PRINCIPAL = "thrift-test-principal";
  public static final String PASSWORD = "super secret password";
  public static final String REALM = "thrift-test-realm";

  public static final String UNWRAPPED_MECHANISM = "CRAM-MD5";
  public static final @Nullable Map<String, String> UNWRAPPED_PROPS = null;

  public static final String WRAPPED_MECHANISM = "DIGEST-MD5";
  public static final Map<String, String> WRAPPED_PROPS = new HashMap<String, String>();

  static {
    WRAPPED_PROPS.put(Sasl.QOP, "auth-int");
    WRAPPED_PROPS.put("com.sun.security.sasl.digest.realm", REALM);
  }

  private static final String testMessage1 =
      "Hello, world! Also, four "
          + "score and seven years ago our fathers brought forth on this "
          + "continent a new nation, conceived in liberty, and dedicated to the "
          + "proposition that all men are created equal.";

  private static final String testMessage2 =
      "I have a dream that one day "
          + "this nation will rise up and live out the true meaning of its creed: "
          + "'We hold these truths to be self-evident, that all men are created equal.'";

  private static Map<String, String> nonNullProps(@Nullable Map<String, String> props) {
    return props == null ? new HashMap<>() : props;
  }

  public static class TestSaslCallbackHandler implements CallbackHandler {
    private final String password;

    public TestSaslCallbackHandler(String password) {
      this.password = password;
    }

    @Override
    public void handle(Callback[] callbacks) throws IOException, UnsupportedCallbackException {
      for (Callback c : callbacks) {
        if (c instanceof NameCallback nameCallback) {
          nameCallback.setName(PRINCIPAL);
        } else if (c instanceof PasswordCallback passwordCallback) {
          passwordCallback.setPassword(password.toCharArray());
        } else if (c instanceof AuthorizeCallback authorizeCallback) {
          authorizeCallback.setAuthorized(true);
        } else if (c instanceof RealmCallback realmCallback) {
          realmCallback.setText(REALM);
        } else {
          throw new UnsupportedCallbackException(c);
        }
      }
    }
  }

  private static class ServerThread extends Thread {
    final String mechanism;
    final @Nullable Map<String, String> props;
    volatile @Nullable Throwable thrown;

    ServerThread(String mechanism, @Nullable Map<String, String> props) {
      this.mechanism = mechanism;
      this.props = props;
    }

    @Override
    public void run() {
      try {
        internalRun();
      } catch (Throwable t) {
        thrown = t;
      }
    }

    private void internalRun() throws Exception {
      try (TServerSocket serverSocket =
          new TServerSocket(
              new TServerSocket.ServerSocketTransportArgs().port(ServerTestBase.PORT))) {
        acceptAndWrite(serverSocket);
      }
    }

    private void acceptAndWrite(TServerSocket serverSocket) throws Exception {
      TTransport serverTransport = serverSocket.accept();
      Map<String, String> effectiveProps = nonNullProps(props);
      TTransport saslServerTransport =
          new TSaslServerTransport(
              mechanism,
              SERVICE,
              HOST,
              effectiveProps,
              new TestSaslCallbackHandler(PASSWORD),
              serverTransport);

      saslServerTransport.open();

      byte[] inBuf = new byte[testMessage1.getBytes(StandardCharsets.UTF_8).length];
      // Deliberately read less than the full buffer to ensure
      // that TSaslTransport is correctly buffering reads. This
      // will fail for the WRAPPED test, if it doesn't work.
      saslServerTransport.readAll(inBuf, 0, 5);
      saslServerTransport.readAll(inBuf, 5, 10);
      saslServerTransport.readAll(inBuf, 15, inBuf.length - 15);
      LOGGER.debug("server got: {}", new String(inBuf, StandardCharsets.UTF_8));
      assertEquals(new String(inBuf, StandardCharsets.UTF_8), testMessage1);

      LOGGER.debug("server writing: {}", testMessage2);
      saslServerTransport.write(testMessage2.getBytes(StandardCharsets.UTF_8));
      saslServerTransport.flush();

      saslServerTransport.close();
    }
  }

  private void testSaslOpen(final String mechanism, final @Nullable Map<String, String> props)
      throws Exception {
    ServerThread serverThread = new ServerThread(mechanism, props);
    serverThread.start();

    try {
      Thread.sleep(1000);
    } catch (InterruptedException e) {
      // Ah well.
    }

    try {
      TSocket clientSocket = new TSocket(HOST, ServerTestBase.PORT);
      Map<String, String> effectiveProps = nonNullProps(props);
      TTransport saslClientTransport =
          new TSaslClientTransport(
              mechanism,
              PRINCIPAL,
              SERVICE,
              HOST,
              effectiveProps,
              new TestSaslCallbackHandler(PASSWORD),
              clientSocket);
      saslClientTransport.open();
      LOGGER.debug("client writing: {}", testMessage1);
      saslClientTransport.write(testMessage1.getBytes(StandardCharsets.UTF_8));
      saslClientTransport.flush();

      byte[] inBuf = new byte[testMessage2.getBytes(StandardCharsets.UTF_8).length];
      saslClientTransport.readAll(inBuf, 0, inBuf.length);
      LOGGER.debug("client got: {}", new String(inBuf, StandardCharsets.UTF_8));
      assertEquals(new String(inBuf, StandardCharsets.UTF_8), testMessage2);

      TTransportException expectedException = null;
      try {
        saslClientTransport.open();
      } catch (TTransportException e) {
        expectedException = e;
      }
      assertNotNull(expectedException);

      saslClientTransport.close();
    } catch (Exception e) {
      LOGGER.warn("Exception caught", e);
      throw e;
    } finally {
      serverThread.interrupt();
      boolean interrupted = false;
      while (true) {
        try {
          serverThread.join();
          break;
        } catch (InterruptedException e) {
          interrupted = true;
        }
      }
      if (interrupted) {
        Thread.currentThread().interrupt();
      }
      assertNull(serverThread.thrown);
    }
  }

  @Test
  public void testUnwrappedOpen() throws Exception {
    testSaslOpen(UNWRAPPED_MECHANISM, UNWRAPPED_PROPS);
  }

  @Test
  public void testWrappedOpen() throws Exception {
    testSaslOpen(WRAPPED_MECHANISM, WRAPPED_PROPS);
  }

  @Test
  public void testAnonymousOpen() throws Exception {
    testSaslOpen("ANONYMOUS", null);
  }

  /**
   * Test that we get the proper exceptions thrown back the server when the client provides invalid
   * password.
   */
  @Test
  public void testBadPassword() throws Exception {
    ServerThread serverThread = new ServerThread(UNWRAPPED_MECHANISM, UNWRAPPED_PROPS);
    serverThread.start();

    try {
      Thread.sleep(1000);
    } catch (InterruptedException e) {
      // Ah well.
    }

    TTransportException tte =
        assertThrows(
            TTransportException.class,
            () -> {
              TSocket clientSocket = new TSocket(HOST, ServerTestBase.PORT);
              Map<String, String> effectiveProps = nonNullProps(UNWRAPPED_PROPS);
              TTransport saslClientTransport =
                  new TSaslClientTransport(
                      UNWRAPPED_MECHANISM,
                      PRINCIPAL,
                      SERVICE,
                      HOST,
                      effectiveProps,
                      new TestSaslCallbackHandler("NOT THE PASSWORD"),
                      clientSocket);
              saslClientTransport.open();
            },
            "Was able to open transport with bad password");
    LOGGER.error("Exception for bad password", tte);
    String errorMessage = tte.getMessage();
    assertNotNull(errorMessage);
    assertTrue(errorMessage.contains("Invalid response"));
    serverThread.interrupt();
    boolean interrupted = false;
    while (true) {
      try {
        serverThread.join();
        break;
      } catch (InterruptedException e) {
        interrupted = true;
      }
    }
    if (interrupted) {
      Thread.currentThread().interrupt();
    }
    Throwable thrown = serverThread.thrown;
    assertNotNull(thrown);
    assertNotNull(thrown.getMessage());
    assertTrue(thrown.getMessage().contains("Invalid response"));
  }

  @Test
  public void testWithServer() throws Exception {
    new TestTSaslTransportsWithServer().testIt();
  }

  public static class TestTSaslTransportsWithServer extends ServerTestBase {

    private @Nullable Thread serverThread;
    private @Nullable TServer server;

    @Override
    public TTransport getClientTransport(TTransport underlyingTransport) throws Exception {
      return new TSaslClientTransport(
          WRAPPED_MECHANISM,
          PRINCIPAL,
          SERVICE,
          HOST,
          WRAPPED_PROPS,
          new TestSaslCallbackHandler(PASSWORD),
          underlyingTransport);
    }

    @Override
    public void startServer(
        final TProcessor processor,
        final TProtocolFactory protoFactory,
        final @Nullable TTransportFactory factory)
        throws Exception {
      serverThread =
          new Thread() {
            @Override
            public void run() {
              try {
                // Transport
                TServerSocket socket =
                    new TServerSocket(new TServerSocket.ServerSocketTransportArgs().port(PORT));

                TTransportFactory serverFactory =
                    new TSaslServerTransport.Factory(
                        WRAPPED_MECHANISM,
                        SERVICE,
                        HOST,
                        WRAPPED_PROPS,
                        new TestSaslCallbackHandler(PASSWORD));
                server =
                    new TSimpleServer(
                        new Args(socket)
                            .processor(processor)
                            .transportFactory(serverFactory)
                            .protocolFactory(protoFactory));

                // Run it
                LOGGER.debug("Starting the server on port {}", PORT);
                server.serve();
              } catch (Exception e) {
                e.printStackTrace();
                fail(e);
              }
            }
          };
      serverThread.start();
      Thread.sleep(1000);
    }

    @Override
    public void stopServer() throws Exception {
      if (server != null) {
        server.stop();
      }
      if (serverThread != null) {
        boolean interrupted = false;
        while (true) {
          try {
            serverThread.join();
            break;
          } catch (InterruptedException e) {
            interrupted = true;
          }
        }
        if (interrupted) {
          Thread.currentThread().interrupt();
        }
      }
    }
  }

  /** Implementation of SASL ANONYMOUS, used for testing client-side initial responses. */
  private static class AnonymousClient implements SaslClient {
    private final String username;
    private boolean hasProvidedInitialResponse;

    AnonymousClient(String username) {
      this.username = username;
    }

    @Override
    public String getMechanismName() {
      return "ANONYMOUS";
    }

    @Override
    public boolean hasInitialResponse() {
      return true;
    }

    @Override
    public byte[] evaluateChallenge(byte[] challenge) throws SaslException {
      if (hasProvidedInitialResponse) {
        throw new SaslException("Already complete!");
      }

      hasProvidedInitialResponse = true;
      return username.getBytes(StandardCharsets.UTF_8);
    }

    @Override
    public boolean isComplete() {
      return hasProvidedInitialResponse;
    }

    @Override
    public byte[] unwrap(byte[] incoming, int offset, int len) {
      throw new UnsupportedOperationException();
    }

    @Override
    public byte[] wrap(byte[] outgoing, int offset, int len) {
      throw new UnsupportedOperationException();
    }

    @Override
    public @Nullable Object getNegotiatedProperty(String propName) {
      return null;
    }

    @Override
    public void dispose() {}
  }

  private static class AnonymousServer implements SaslServer {
    private @Nullable String user;

    @Override
    public String getMechanismName() {
      return "ANONYMOUS";
    }

    @Override
    @SuppressWarnings("NullAway")
    public byte[] evaluateResponse(byte[] response) throws SaslException {
      this.user = new String(response, StandardCharsets.UTF_8);
      return null;
    }

    @Override
    public boolean isComplete() {
      return user != null;
    }

    @Override
    public @Nullable String getAuthorizationID() {
      return user;
    }

    @Override
    public byte[] unwrap(byte[] incoming, int offset, int len) {
      throw new UnsupportedOperationException();
    }

    @Override
    public byte[] wrap(byte[] outgoing, int offset, int len) {
      throw new UnsupportedOperationException();
    }

    @Override
    public @Nullable Object getNegotiatedProperty(String propName) {
      return null;
    }

    @Override
    public void dispose() {}
  }

  public static class SaslAnonymousFactory implements SaslClientFactory, SaslServerFactory {

    @Override
    public @Nullable SaslClient createSaslClient(
        String[] mechanisms,
        String authorizationId,
        String protocol,
        String serverName,
        Map<String, ?> props,
        CallbackHandler cbh) {
      for (String mech : mechanisms) {
        if ("ANONYMOUS".equals(mech)) {
          return new AnonymousClient(authorizationId);
        }
      }
      return null;
    }

    @Override
    public @Nullable SaslServer createSaslServer(
        String mechanism,
        String protocol,
        String serverName,
        Map<String, ?> props,
        CallbackHandler cbh) {
      if ("ANONYMOUS".equals(mechanism)) {
        return new AnonymousServer();
      }
      return null;
    }

    @Override
    public String[] getMechanismNames(Map<String, ?> props) {
      return new String[] {"ANONYMOUS"};
    }
  }

  static {
    java.security.Security.addProvider(new SaslAnonymousProvider());
  }

  public static class SaslAnonymousProvider extends java.security.Provider {
    public SaslAnonymousProvider() {
      super("ThriftSaslAnonymous", "1.0", "Thrift Anonymous SASL provider");
      put("SaslClientFactory.ANONYMOUS", SaslAnonymousFactory.class.getName());
      put("SaslServerFactory.ANONYMOUS", SaslAnonymousFactory.class.getName());
    }
  }

  private static class MockTTransport extends TTransport {

    private final byte[] badHeader;
    private final TMemoryInputTransport readBuffer;

    MockTTransport(int mode) throws TTransportException {
      readBuffer = new TMemoryInputTransport();
      if (mode == 1) {
        // Invalid status byte
        badHeader = new byte[] {(byte) 0xFF, (byte) 0x00, (byte) 0x00, (byte) 0x00, (byte) 0x05};
      } else if (mode == 2) {
        // Valid status byte, negative payload length
        badHeader = new byte[] {(byte) 0x01, (byte) 0xFF, (byte) 0xFF, (byte) 0xFF, (byte) 0xFF};
      } else if (mode == 3) {
        // Valid status byte, excessively large, bogus payload length
        badHeader = new byte[] {(byte) 0x01, (byte) 0x64, (byte) 0x00, (byte) 0x00, (byte) 0x00};
      } else {
        badHeader = new byte[0];
      }
      readBuffer.reset(badHeader);
    }

    @Override
    public boolean isOpen() {
      return true;
    }

    @Override
    public void open() throws TTransportException {}

    @Override
    public void close() {}

    @Override
    public int read(byte[] buf, int off, int len) throws TTransportException {
      return readBuffer.read(buf, off, len);
    }

    @Override
    public void write(byte[] buf, int off, int len) throws TTransportException {}

    @Override
    public TConfiguration getConfiguration() {
      return readBuffer.getConfiguration();
    }

    @Override
    public void updateKnownMessageSize(long size) throws TTransportException {
      readBuffer.updateKnownMessageSize(size);
    }

    @Override
    public void checkReadBytesAvailable(long numBytes) throws TTransportException {
      readBuffer.checkReadBytesAvailable(numBytes);
    }
  }

  @Test
  public void testBadHeader() {
    TSaslTransport saslTransport;
    try {
      saslTransport = new TSaslServerTransport(new MockTTransport(1));
      saslTransport.receiveSaslMessage();
      fail("Should have gotten an error due to incorrect status byte value.");
    } catch (TTransportException e) {
      assertEquals(e.getMessage(), "Invalid status -1");
    }
    try {
      saslTransport = new TSaslServerTransport(new MockTTransport(2));
      saslTransport.receiveSaslMessage();
      fail("Should have gotten an error due to negative payload length.");
    } catch (TTransportException e) {
      assertEquals(e.getMessage(), "Invalid payload header length: -1");
    }
    try {
      saslTransport = new TSaslServerTransport(new MockTTransport(3));
      saslTransport.receiveSaslMessage();
      fail("Should have gotten an error due to bogus (large) payload length.");
    } catch (TTransportException e) {
      assertEquals(e.getMessage(), "Invalid payload header length: 1677721600");
    }
  }
}
