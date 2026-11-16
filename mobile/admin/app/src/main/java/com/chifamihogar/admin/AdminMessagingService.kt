package com.chifamihogar.admin

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.net.Uri
import com.google.firebase.messaging.FirebaseMessaging
import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage

object PushNotifications {
    const val CHANNEL = "admin_orders"
    private fun settings(context: Context) = context.getSharedPreferences("push_preferences", Context.MODE_PRIVATE)
    fun enabled(context: Context) = settings(context).getBoolean("enabled", false)
    fun channel(context: Context) {
        context.getSystemService(NotificationManager::class.java).createNotificationChannel(
            NotificationChannel(CHANNEL, "Nuevos pedidos", NotificationManager.IMPORTANCE_HIGH).apply {
                description = "Avisos de pedidos para el administrador"
                lockscreenVisibility = Notification.VISIBILITY_PRIVATE
            })
    }
    fun allowed(context: Context): Boolean {
        val manager = context.getSystemService(NotificationManager::class.java)
        return manager.areNotificationsEnabled() &&
            manager.getNotificationChannel(CHANNEL)?.importance != NotificationManager.IMPORTANCE_NONE
    }
    fun activate(context: Context, result: (Boolean) -> Unit) {
        channel(context)
        if (!allowed(context) || TokenVault(context).load() == null) { result(false); return }
        settings(context).edit().putBoolean("enabled", true).apply()
        FirebaseMessaging.getInstance().isAutoInitEnabled = true
        PushRegistration.sync(context, force = true, result = result)
    }
    fun stop(context: Context, result: ((Boolean) -> Unit)? = null) {
        settings(context).edit().putBoolean("enabled", false).apply()
        FirebaseMessaging.getInstance().isAutoInitEnabled = false
        context.getSystemService(NotificationManager::class.java).cancelAll()
        PushRegistration.sync(context, force = true, result = result)
    }
    fun show(context: Context, eventId: String, orderId: Long?) {
        if (!enabled(context) || !allowed(context) || TokenVault(context).load() == null) return
        channel(context)
        val intent = Intent(context, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
            data = Uri.parse("chifa-admin://notification/" + Uri.encode(eventId))
            if (orderId != null) putExtra("push_order_id", orderId)
        }
        val pending = PendingIntent.getActivity(context, 0, intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE)
        val notification = Notification.Builder(context, CHANNEL)
            .setSmallIcon(R.drawable.ic_notification)
            .setContentTitle("Chifa Mi Hogar")
            .setContentText(if (orderId == null) "Tienes un nuevo aviso." else "Hay un nuevo pedido. Toca para revisarlo.")
            .setVisibility(Notification.VISIBILITY_PRIVATE)
            .setAutoCancel(true).setOnlyAlertOnce(true).setContentIntent(pending).build()
        try {
            context.getSystemService(NotificationManager::class.java).notify(eventId, 0, notification)
        } catch (_: SecurityException) {
            // Permission can be withdrawn between the check and notify().
        }
    }
}

class AdminMessagingService : FirebaseMessagingService() {
    override fun onMessageReceived(message: RemoteMessage) {
        val id = PushMessage.orderId(message.data["order_id"])
        if (message.notification == null && message.data["type"] != "new_order") return
        val event = if (id != null) "order:" + id else message.messageId ?: return
        PushNotifications.show(this, event, id)
    }
    override fun onNewToken(token: String) {
        if (PushNotifications.enabled(this)) PushRegistration.sync(this, force = true)
    }
}

object PushMessage {
    fun orderId(value: String?): Long? = value?.toLongOrNull()?.takeIf { it > 0 }
}
