package com.chifamihogar.admin

import org.json.JSONObject
import org.junit.Assert.*
import org.junit.Test

class ApiContractTest {
    private fun login() = JSONObject("""{"token_type":"Bearer","access_token":"1|secret","expires_at":"2026-12-15T12:00:00Z","user":{"role":"admin"}}""")
    @Test fun readsAdminSession() {
        assertEquals("1|secret", ApiContract.session(login()).token)
    }
    @Test(expected = IllegalArgumentException::class) fun rejectsCustomerSession() {
        val json = login()
        json.getJSONObject("user").put("role", "customer")
        ApiContract.session(json)
    }
    @Test(expected = IllegalArgumentException::class) fun rejectsHeaderInjection() {
        ApiContract.session(login().put("access_token", "secret\r\nInjected: value"))
    }
    @Test fun preservesServerMoneyAndPagination() {
        val page = ApiContract.orders(JSONObject("""{"data":[{"id":7,"total":"22.99","customer_name":"<script>"}],"meta":{"current_page":2,"last_page":4,"total":65}}"""))
        assertEquals("22.99", page.orders.single().text("total"))
        assertEquals("<script>", page.orders.single().text("customer_name"))
        assertEquals(2, page.page)
        assertEquals(4, page.lastPage)
        assertEquals(65, page.total)
    }
    @Test fun handlesEmptyPageAndNullableFields() {
        assertTrue(ApiContract.orders(JSONObject("""{"data":[],"meta":{"current_page":1,"last_page":1,"total":0}}""")).orders.isEmpty())
        assertEquals("", JSONObject("""{"reference":null}""").text("reference"))
        assertTrue(JSONObject().rows("extras").isEmpty())
    }
    @Test fun mapsFailuresWithoutShowingServerBodies() {
        assertTrue(ApiContract.errorText(401).contains("sesión"))
        assertTrue(ApiContract.errorText(403).contains("acceso"))
        assertTrue(ApiContract.errorText(429).contains("Espera"))
        assertTrue(ApiContract.errorText(500).contains("conexión"))
    }
}
