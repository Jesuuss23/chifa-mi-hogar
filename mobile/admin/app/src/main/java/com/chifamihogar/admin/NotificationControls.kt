package com.chifamihogar.admin

import android.Manifest
import android.content.ClipData
import android.content.ClipboardManager
import android.content.Context
import android.content.Intent
import android.os.Build
import android.os.PersistableBundle
import android.provider.Settings
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.result.contract.ActivityResultContracts
import androidx.compose.foundation.layout.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import com.google.firebase.messaging.FirebaseMessaging

@Composable
fun NotificationControls() {
    val context = LocalContext.current
    var open by remember { mutableStateOf(false) }
    var working by remember { mutableStateOf(false) }
    var message by remember { mutableStateOf("") }
    fun activate() {
        working = true
        PushNotifications.activate(context) { success ->
            working = false
            message = if (success) "Teléfono registrado para recibir pedidos."
                else "No pudimos registrar el teléfono. Revisa la conexión y que los avisos estén habilitados en el servidor."
        }
    }
    val permission = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) { granted ->
        if (granted) activate() else message = "Puedes seguir usando la app sin notificaciones."
    }
    TextButton(onClick = { open = true }) { Text("Avisos") }
    if (open) AlertDialog(
        onDismissRequest = { if (!working) open = false },
        title = { Text("Notificaciones") },
        text = {
            Column(verticalArrangement = Arrangement.spacedBy(10.dp)) {
                Text("Permite avisos de nuevos pedidos en este teléfono.")
                if (message.isNotBlank()) Text(message)
                Button(enabled = !working, onClick = {
                    PushNotifications.channel(context)
                    if (Build.VERSION.SDK_INT >= 33 && !PushNotifications.allowed(context)) {
                        permission.launch(Manifest.permission.POST_NOTIFICATIONS)
                    } else activate()
                }) { Text(if (working) "Preparando…" else "Permitir avisos") }
                TextButton(onClick = {
                    context.startActivity(Intent(Settings.ACTION_APP_NOTIFICATION_SETTINGS)
                        .putExtra(Settings.EXTRA_APP_PACKAGE, context.packageName))
                }) { Text("Abrir ajustes del teléfono") }
                TextButton(enabled = !working, onClick = {
                    working = true
                    PushNotifications.stop(context) { success ->
                        working = false
                        message = if (success) "Avisos desactivados."
                            else "Desactivados en este teléfono. Sincronización pendiente: reabre la app con conexión."
                    }
                }) { Text("Desactivar avisos") }
                if (BuildConfig.DEBUG) {
                    Text("Diagnóstico de Firebase", style = MaterialTheme.typography.bodySmall)
                    OutlinedButton(enabled = !working && PushNotifications.enabled(context), onClick = {
                        working = true
                        FirebaseMessaging.getInstance().token.addOnCompleteListener { task ->
                            working = false
                            if (task.isSuccessful && PushNotifications.enabled(context)) {
                                val clip = ClipData.newPlainText("Token de prueba FCM", task.result)
                                if (Build.VERSION.SDK_INT >= 33) {
                                    clip.description.extras = PersistableBundle().apply {
                                        putBoolean("android.content.extra.IS_SENSITIVE", true)
                                    }
                                }
                                context.getSystemService(ClipboardManager::class.java).setPrimaryClip(clip)
                                message = "Token copiado. Pégalo solo en el envío de prueba de Firebase."
                            } else message = "No pudimos obtener el token. Reintenta con conexión."
                        }
                    }) { Text("Copiar token de prueba") }
                }
            }
        },
        confirmButton = { TextButton(enabled = !working, onClick = { open = false }) { Text("Listo") } })
}
