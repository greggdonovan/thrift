package org.apache.thrift;

import static org.junit.jupiter.api.Assertions.assertNotSame;

import org.junit.jupiter.api.Test;
import thrift.test.DeepCopyBar;
import thrift.test.DeepCopyFoo;

public class TestDeepCopy {

  @Test
  public void testDeepCopy() throws Exception {
    final DeepCopyFoo foo = new DeepCopyFoo();

    foo.addToL(new DeepCopyBar());
    foo.addToS(new DeepCopyBar());
    foo.putToM("test 3", new DeepCopyBar());

    foo.addToLi(new thrift.test.Object());
    foo.addToSi(new thrift.test.Object());
    foo.putToMi("test 3", new thrift.test.Object());

    foo.setBar(new DeepCopyBar());

    final DeepCopyFoo deepCopyFoo = foo.deepCopy();

    assertNotSame(foo.getBar().orElseThrow(), deepCopyFoo.getBar().orElseThrow());

    assertNotSame(foo.getL().orElseThrow().get(0), deepCopyFoo.getL().orElseThrow().get(0));
    assertNotSame(
        foo.getS().orElseThrow().toArray(new DeepCopyBar[0])[0],
        deepCopyFoo.getS().orElseThrow().toArray(new DeepCopyBar[0])[0]);
    assertNotSame(foo.getM().orElseThrow().get("test 3"), deepCopyFoo.getM().orElseThrow().get("test 3"));

    assertNotSame(foo.getLi().orElseThrow().get(0), deepCopyFoo.getLi().orElseThrow().get(0));
    assertNotSame(
        foo.getSi().orElseThrow().toArray(new thrift.test.Object[0])[0],
        deepCopyFoo.getSi().orElseThrow().toArray(new thrift.test.Object[0])[0]);
    assertNotSame(foo.getMi().orElseThrow().get("test 3"), deepCopyFoo.getMi().orElseThrow().get("test 3"));
  }
}
