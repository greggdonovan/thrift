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

import java.io.FileInputStream;
import java.io.FileOutputStream;
import java.io.IOException;
import java.io.InputStream;
import java.io.OutputStream;
import java.io.RandomAccessFile;

@SuppressWarnings("NullAway") // Fields initialized in constructor, null indicates closed state
public class TStandardFile implements TSeekableFile {

  protected String path_;
  protected RandomAccessFile inputFile_;

  public TStandardFile(String path) throws IOException {
    path_ = path;
    inputFile_ = new RandomAccessFile(path_, "r");
  }

  @Override
  public InputStream getInputStream() throws IOException {
    return new FileInputStream(inputFile_.getFD());
  }

  @Override
  public OutputStream getOutputStream() throws IOException {
    return new FileOutputStream(path_);
  }

  @Override
  public void close() throws IOException {
    if (inputFile_ != null) {
      inputFile_.close();
    }
  }

  @Override
  public long length() throws IOException {
    return inputFile_.length();
  }

  @Override
  public void seek(long pos) throws IOException {
    inputFile_.seek(pos);
  }
}
