package com.chifamihogar.admin

import android.app.Application
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.launch
import kotlinx.coroutines.withContext
import org.json.JSONObject

data class AdminState(
    val checking: Boolean = true,
    val admin: Admin? = null,
    val busy: Boolean = false,
    val error: String? = null,
    val orders: OrderPage? = null,
    val detail: JSONObject? = null,
    val status: String = "",
    val search: String = "",
    val selectedId: Long? = null,
    val canRestore: Boolean = false,
    val blockedUntil: Long = 0
)

class AdminViewModel(app: Application) : AndroidViewModel(app) {
    private val api = AdminApi()
    private val vault = TokenVault(app)
    private var session: Session? = null
    private var pendingOrderId: Long? = null
    private val mutable = MutableStateFlow(AdminState())
    val state = mutable.asStateFlow()

    init { restore() }

    private fun run(action: suspend () -> Unit) {
        if (mutable.value.busy) return
        if (System.currentTimeMillis() < mutable.value.blockedUntil) {
            mutable.value = mutable.value.copy(error = "Espera antes de volver a intentar.")
            return
        }
        mutable.value = mutable.value.copy(busy = true, error = null)
        viewModelScope.launch {
            try { action() }
            catch (cancel: CancellationException) { throw cancel }
            catch (error: Exception) {
                val code = (error as? ApiFailure)?.status ?: 0
                if (code == 401 || code == 403) {
                    session = null
                    PushNotifications.stop(getApplication())
                    withContext(Dispatchers.IO) { vault.clear() }
                    mutable.value = AdminState(checking = false, error = ApiContract.errorText(code))
                } else {
                    mutable.value = mutable.value.copy(
                        error = if (code == 422 && mutable.value.admin == null) "Correo o contraseña incorrectos."
                            else ApiContract.errorText(code),
                        blockedUntil = if (code == 429) System.currentTimeMillis() +
                            (error as ApiFailure).retrySeconds * 1000 else 0)
                }
            } finally {
                mutable.value = mutable.value.copy(busy = false, checking = false)
                openPendingNotification()
            }
        }
    }

    private fun restore() = run {
        session = withContext(Dispatchers.IO) { vault.load() }
        if (session == null) PushNotifications.stop(getApplication())
        mutable.value = mutable.value.copy(canRestore = session != null)
        session?.let {
            mutable.value = mutable.value.copy(admin = api.me(it.token))
            loadOrders()
        }
    }
    fun retry() {
        if (mutable.value.admin == null && session != null) restore()
        else if (mutable.value.selectedId != null) open(mutable.value.selectedId!!)
        else refresh()
    }
    fun login(email: String, password: String) = run {
        val authenticated = api.login(email, password)
        try { withContext(Dispatchers.IO) { vault.save(authenticated) } }
        catch (error: Exception) {
            // Best effort revoke if secure local storage failed.
            try { api.logout(authenticated.token) } catch (_: Exception) { }
            throw error
        }
        session = authenticated
        mutable.value = mutable.value.copy(canRestore = true)
        mutable.value = mutable.value.copy(admin = api.me(authenticated.token))
        loadOrders()
    }
    private suspend fun loadOrders(page: Int = 1) {
        val token = session?.token ?: return
        val snapshot = mutable.value
        val result = api.orders(token, page, snapshot.status, snapshot.search)
        mutable.value = mutable.value.copy(orders = result)
    }
    fun filter(status: String, search: String) = run {
        mutable.value = mutable.value.copy(status = status, search = search.take(100), orders = null)
        loadOrders()
    }
    fun page(page: Int) = run { loadOrders(page) }
    fun refresh() = run {
        val token = session?.token ?: return@run
        mutable.value = mutable.value.copy(admin = api.me(token))
        val id = mutable.value.selectedId
        if (id != null) mutable.value = mutable.value.copy(detail = api.order(token, id))
        else loadOrders(mutable.value.orders?.page ?: 1)
    }
    fun open(id: Long) = run {
        val token = session?.token ?: return@run
        mutable.value = mutable.value.copy(selectedId = id, detail = null)
        mutable.value = mutable.value.copy(detail = api.order(token, id))
    }
    fun notificationOrder(id: Long) {
        if (id <= 0) return
        pendingOrderId = id
        openPendingNotification()
    }
    private fun openPendingNotification() {
        if (mutable.value.busy || mutable.value.admin == null || session == null ||
            System.currentTimeMillis() < mutable.value.blockedUntil) return
        val id = pendingOrderId ?: return
        pendingOrderId = null
        open(id)
    }
    fun back() {
        if (!mutable.value.busy) mutable.value = mutable.value.copy(selectedId = null, detail = null, error = null)
    }
    fun logout() = run {
        session?.let { api.logout(it.token) }
        PushNotifications.stop(getApplication())
        pendingOrderId = null
        withContext(Dispatchers.IO) { vault.clear() }
        session = null
        mutable.value = AdminState(checking = false)
    }
}
