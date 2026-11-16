package com.chifamihogar.admin

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONObject
import java.io.IOException
import java.net.URI
import java.net.URLEncoder
import javax.net.ssl.HttpsURLConnection

class ApiFailure(val status: Int, val retrySeconds: Long = 0) : IOException("API request failed")
data class Session(val token: String, val expiresAt: String) {
    override fun toString() = "Session(token=[REDACTED], expiresAt=$expiresAt)"
}
data class Admin(val name: String)
data class OrderPage(val orders: List<JSONObject>, val page: Int, val lastPage: Int, val total: Int)

object ApiContract {
    fun session(json: JSONObject): Session {
        require(json.getString("token_type") == "Bearer")
        require(json.getJSONObject("user").getString("role") == "admin")
        val token = json.getString("access_token")
        require(token.isNotBlank() && !token.contains('\n') && !token.contains('\r'))
        val expires = json.getString("expires_at")
        java.time.Instant.parse(expires)
        return Session(token, expires)
    }
    fun orders(json: JSONObject): OrderPage {
        val meta = json.getJSONObject("meta")
        val data = json.getJSONArray("data")
        return OrderPage((0 until data.length()).map { data.getJSONObject(it) },
            meta.getInt("current_page"), meta.getInt("last_page"), meta.getInt("total"))
    }
    fun errorText(code: Int): String = when (code) {
        401 -> "Tu sesión venció. Ingresa nuevamente."
        403 -> "Esta cuenta no tiene acceso de administrador."
        404 -> "El pedido ya no está disponible."
        422 -> "Revisa los datos ingresados."
        429 -> "Demasiados intentos. Espera antes de reintentar."
        else -> "No pudimos conectar. Revisa tu conexión y reintenta."
    }
}

class AdminApi {
    private suspend fun request(path: String, token: String? = null, body: JSONObject? = null): JSONObject =
        withContext(Dispatchers.IO) {
            val base = URI(BuildConfig.API_BASE_URL)
            require(base.scheme == "https" && base.host == "chifamihogar.com")
            val url = base.resolve(path)
            require(url.host == base.host && url.scheme == base.scheme && url.path.startsWith(base.path))
            val connection = url.toURL().openConnection() as HttpsURLConnection
            try {
                connection.connectTimeout = 15000
                connection.readTimeout = 15000
                connection.instanceFollowRedirects = false
                connection.useCaches = false
                connection.setRequestProperty("Accept", "application/json")
                if (token != null) connection.setRequestProperty("Authorization", "Bearer " + token)
                if (body != null) {
                    connection.requestMethod = "POST"
                    connection.doOutput = true
                    connection.setRequestProperty("Content-Type", "application/json; charset=utf-8")
                    connection.outputStream.use { it.write(body.toString().toByteArray(Charsets.UTF_8)) }
                }
                val code = connection.responseCode
                if (code !in 200..299) {
                    val retry = connection.getHeaderField("Retry-After")?.toLongOrNull()?.coerceIn(1, 86400) ?: 60
                    throw ApiFailure(code, if (code == 429) retry else 0)
                }
                if (code == 204) return@withContext JSONObject()
                require(connection.contentType?.contains("application/json", ignoreCase = true) == true)
                val bytes = connection.inputStream.use { input ->
                    val out = java.io.ByteArrayOutputStream()
                    val buffer = ByteArray(8192)
                    while (true) {
                        val read = input.read(buffer)
                        if (read < 0) break
                        if (out.size() + read > 4 * 1024 * 1024) throw IOException("Response too large")
                        out.write(buffer, 0, read)
                    }
                    out.toByteArray()
                }
                JSONObject(String(bytes, Charsets.UTF_8))
            } finally {
                connection.disconnect()
            }
        }

    suspend fun login(email: String, password: String): Session = ApiContract.session(request("auth/login",
        body = JSONObject().put("email", email.trim()).put("password", password)
            .put("device_name", ("Android " + android.os.Build.MODEL).take(80))))
    suspend fun me(token: String): Admin {
        val identity = request("auth/me", token).getJSONObject("data")
        if (identity.getString("role") != "admin") throw ApiFailure(403)
        return Admin(identity.getString("name"))
    }
    suspend fun logout(token: String) { request("auth/logout", token, JSONObject()) }
    suspend fun orders(token: String, page: Int, status: String, search: String): OrderPage {
        val query = "orders?per_page=20&page=" + page +
            (if (status.isEmpty()) "" else "&status=" + URLEncoder.encode(status, "UTF-8")) +
            (if (search.isBlank()) "" else "&search=" + URLEncoder.encode(search.take(100), "UTF-8"))
        return ApiContract.orders(request(query, token))
    }
    suspend fun order(token: String, id: Long): JSONObject {
        require(id > 0)
        return request("orders/" + id, token).getJSONObject("data")
    }
}

fun JSONObject.text(key: String): String = if (isNull(key)) "" else optString(key, "")
fun JSONObject.rows(key: String): List<JSONObject> {
    val list = optJSONArray(key) ?: return emptyList()
    return (0 until list.length()).map { list.getJSONObject(it) }
}
