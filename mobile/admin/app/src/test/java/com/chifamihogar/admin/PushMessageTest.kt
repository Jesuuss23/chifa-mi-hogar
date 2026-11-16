package com.chifamihogar.admin

import org.junit.Assert.*
import org.junit.Test

class PushMessageTest {
    @Test fun acceptsOnlyPositiveOrderIds() {
        assertEquals(25L, PushMessage.orderId("25"))
        for (value in listOf(null, "", "0", "-1", "abc", "https://evil.example", "9223372036854775808")) {
            assertNull(PushMessage.orderId(value))
        }
    }
}
