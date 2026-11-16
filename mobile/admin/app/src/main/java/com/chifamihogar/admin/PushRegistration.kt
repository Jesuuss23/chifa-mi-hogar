package com.chifamihogar.admin

import android.content.Context
import com.google.firebase.messaging.FirebaseMessaging
import kotlinx.coroutines.*
import kotlinx.coroutines.sync.Mutex
import kotlinx.coroutines.sync.withLock
import kotlin.coroutines.resume
import kotlin.coroutines.resumeWithException

/** Serialized, best-effort synchronization. Foreground resumes retry offline changes. */
object PushRegistration {
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)
    private val mutex = Mutex()
    private var lastAttempt = 0L

    fun sync(context: Context, force: Boolean = false, result: ((Boolean) -> Unit)? = null) {
        val app = context.applicationContext
        scope.launch {
            val success = try { withTimeout(45_000) { mutex.withLock {
                val now = android.os.SystemClock.elapsedRealtime()
                if (!force && now - lastAttempt < 60_000) return@withLock true
                lastAttempt = now
                val session = TokenVault(app).load() ?: return@withLock false
                try {
                    val api = AdminApi()
                    if (!PushNotifications.enabled(app) || !PushNotifications.allowed(app)) {
                        api.unregisterPush(session.token)
                        if (!PushNotifications.enabled(app)) {
                            suspendCancellableCoroutine<Unit> { continuation ->
                                FirebaseMessaging.getInstance().deleteToken().addOnCompleteListener { task ->
                                    if (continuation.isActive) {
                                        if (task.isSuccessful) continuation.resume(Unit)
                                        else continuation.resumeWithException(IllegalStateException("FCM unavailable"))
                                    }
                                }
                            }
                        }
                        return@withLock true
                    }
                    val fcm = suspendCancellableCoroutine<String> { continuation ->
                        FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
                            if (continuation.isActive) {
                                if (task.isSuccessful) continuation.resume(task.result)
                                else continuation.resumeWithException(IllegalStateException("FCM unavailable"))
                            }
                        }
                    }
                    if (TokenVault(app).load()?.token != session.token) return@withLock false
                    if (!PushNotifications.enabled(app) || !PushNotifications.allowed(app)) {
                        api.unregisterPush(session.token)
                        return@withLock false
                    }
                    api.registerPush(session.token, fcm)
                    // Opt-out could have happened during the HTTP request.
                    if (!PushNotifications.enabled(app) || !PushNotifications.allowed(app)) {
                        api.unregisterPush(session.token)
                        return@withLock false
                    }
                    TokenVault(app).load()?.token == session.token
                } catch (cancel: CancellationException) {
                    throw cancel
                } catch (_: Exception) {
                    // No token or HTTP body is logged. Retry on next foreground/explicit action.
                    false
                }
            }
            } } catch (_: TimeoutCancellationException) { false }
            if (result != null) withContext(Dispatchers.Main) { result(success) }
        }
    }
}
