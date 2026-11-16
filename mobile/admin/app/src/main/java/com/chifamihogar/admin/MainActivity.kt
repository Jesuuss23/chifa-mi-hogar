package com.chifamihogar.admin

import android.os.Bundle
import android.content.Intent
import android.view.WindowManager
import androidx.activity.ComponentActivity
import androidx.activity.compose.BackHandler
import androidx.activity.compose.setContent
import androidx.activity.enableEdgeToEdge
import androidx.activity.viewModels
import androidx.compose.foundation.background
import androidx.compose.foundation.horizontalScroll
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.text.KeyboardOptions
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.input.KeyboardType
import androidx.compose.ui.text.input.PasswordVisualTransformation
import androidx.compose.ui.text.input.VisualTransformation
import androidx.compose.ui.unit.dp
import androidx.lifecycle.Lifecycle
import androidx.lifecycle.LifecycleEventObserver
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import androidx.lifecycle.compose.LocalLifecycleOwner
import org.json.JSONObject
import java.math.BigDecimal
import java.text.NumberFormat
import java.time.OffsetDateTime
import java.time.ZoneId
import java.time.format.DateTimeFormatter
import java.util.Locale

class MainActivity : ComponentActivity() {
    private val model: AdminViewModel by viewModels()
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        window.setFlags(WindowManager.LayoutParams.FLAG_SECURE, WindowManager.LayoutParams.FLAG_SECURE)
        enableEdgeToEdge()
        PushNotifications.channel(this)
        if (savedInstanceState == null) receiveNotification(intent)
        setContent { AdminApp(model) }
    }
    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        receiveNotification(intent)
    }
    private fun receiveNotification(intent: Intent) {
        val id = try {
            PushMessage.orderId(intent.getStringExtra("order_id"))
                ?: intent.getLongExtra("push_order_id", 0).takeIf { it > 0 }
        } catch (_: RuntimeException) { null }
        id?.let(model::notificationOrder)
    }
}

private val statuses = linkedMapOf("" to "Todos", "pending" to "Pendientes", "confirmed" to "Confirmados",
    "preparing" to "En cocina", "on_the_way" to "En camino", "delivered" to "Entregados", "cancelled" to "Cancelados")
private val sage = lightColorScheme(primary = Color(0xFF46583F), onPrimary = Color.White,
    secondary = Color(0xFF795A20), background = Color(0xFFF8F7F1), surface = Color.White,
    surfaceVariant = Color(0xFFEDF0E8), onSurfaceVariant = Color(0xFF4B5347))
private fun money(value: String): String = try {
    NumberFormat.getCurrencyInstance(Locale.forLanguageTag("es-PE")).format(BigDecimal(value))
} catch (_: Exception) { "—" }
private fun date(value: String): String = try {
    OffsetDateTime.parse(value).atZoneSameInstant(ZoneId.of("America/Lima"))
        .format(DateTimeFormatter.ofPattern("dd MMM · HH:mm", Locale.forLanguageTag("es-PE")))
} catch (_: Exception) { "" }
private fun payment(order: JSONObject): String = when (order.text("payment_status")) {
    "verified" -> "Pago verificado"
    "rejected" -> "Pago rechazado"
    else -> "Pago pendiente"
}

@Composable
private fun AdminApp(model: AdminViewModel) {
    val state by model.state.collectAsStateWithLifecycle()
    val owner = LocalLifecycleOwner.current
    DisposableEffect(owner) {
        val observer = LifecycleEventObserver { _, event ->
            if (event == Lifecycle.Event.ON_RESUME && model.state.value.admin != null) model.refresh()
        }
        owner.lifecycle.addObserver(observer)
        onDispose { owner.lifecycle.removeObserver(observer) }
    }
    BackHandler(state.selectedId != null) { model.back() }
    MaterialTheme(colorScheme = sage) {
        Surface(Modifier.fillMaxSize(), color = MaterialTheme.colorScheme.background) {
            Box(Modifier.fillMaxSize().safeDrawingPadding().imePadding(), contentAlignment = Alignment.TopCenter) {
                if (state.checking) {
                    CircularProgressIndicator(Modifier.align(Alignment.Center))
                } else if (state.admin == null) {
                    Login(state, model)
                } else {
                    Column(Modifier.widthIn(max = 840.dp).fillMaxSize()) {
                        Header(state, model)
                        if (state.busy) LinearProgressIndicator(Modifier.fillMaxWidth())
                        state.error?.let { ErrorNotice(it, state.busy, model::retry) }
                        if (state.selectedId == null) OrderList(state, model) else Detail(state, model)
                    }
                }
            }
        }
    }
}

@Composable
private fun Login(state: AdminState, model: AdminViewModel) {
    var email by remember { mutableStateOf("") }
    var password by remember { mutableStateOf("") }
    var reveal by remember { mutableStateOf(false) }
    LazyColumn(Modifier.widthIn(max = 480.dp).fillMaxWidth(), contentPadding = PaddingValues(24.dp),
        verticalArrangement = Arrangement.spacedBy(16.dp)) {
        item {
            Spacer(Modifier.height(40.dp))
            Text("MI HOGAR", color = MaterialTheme.colorScheme.primary, fontWeight = FontWeight.Bold)
            Text("Tu cocina,\na un vistazo.", style = MaterialTheme.typography.headlineLarge, fontWeight = FontWeight.Bold)
            Text("Administración", modifier = Modifier.padding(top = 12.dp))
        }
        item {
            OutlinedTextField(email, { email = it.take(255) }, label = { Text("Correo") },
                modifier = Modifier.fillMaxWidth(), singleLine = true, enabled = !state.busy,
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Email), shape = RoundedCornerShape(16.dp))
        }
        item {
            OutlinedTextField(password, { password = it.take(255) }, label = { Text("Contraseña") },
                modifier = Modifier.fillMaxWidth(), singleLine = true, enabled = !state.busy,
                keyboardOptions = KeyboardOptions(keyboardType = KeyboardType.Password),
                visualTransformation = if (reveal) VisualTransformation.None else PasswordVisualTransformation(),
                trailingIcon = { TextButton({ reveal = !reveal }) { Text(if (reveal) "Ocultar" else "Ver") } },
                shape = RoundedCornerShape(16.dp))
        }
        item {
            Button(onClick = { model.login(email, password); password = ""; reveal = false },
                enabled = !state.busy && email.isNotBlank() && password.isNotBlank(),
                modifier = Modifier.fillMaxWidth().heightIn(min = 52.dp)) {
                Text(if (state.busy) "Ingresando…" else "Ingresar")
            }
        }
        item {
            state.error?.let {
                if (state.canRestore) ErrorNotice(it, state.busy, model::retry)
                else Text(it, color = MaterialTheme.colorScheme.error)
            }
            Text("Usa tu cuenta de administrador.", style = MaterialTheme.typography.bodySmall)
        }
    }
}

@Composable
private fun Header(state: AdminState, model: AdminViewModel) {
    var logoutDialog by remember { mutableStateOf(false) }
    Row(Modifier.fillMaxWidth().padding(16.dp), verticalAlignment = Alignment.CenterVertically) {
        Column(Modifier.weight(1f)) {
            Text("Mi Hogar", style = MaterialTheme.typography.titleLarge, fontWeight = FontWeight.Bold)
            Text(if (state.selectedId == null) "Pedidos" else "Detalle del pedido", style = MaterialTheme.typography.bodySmall)
        }
        NotificationControls()
        TextButton(onClick = { logoutDialog = true }, enabled = !state.busy) { Text("Salir") }
    }
    if (logoutDialog) AlertDialog(onDismissRequest = { logoutDialog = false },
        title = { Text("¿Cerrar sesión?") },
        text = { Text("Necesitas conexión para revocar el acceso de este teléfono.") },
        confirmButton = { TextButton({ logoutDialog = false; model.logout() }) { Text("Cerrar sesión") } },
        dismissButton = { TextButton({ logoutDialog = false }) { Text("Volver") } })
}

@Composable
private fun ErrorNotice(text: String, busy: Boolean, retry: () -> Unit) {
    Card(Modifier.fillMaxWidth().padding(horizontal = 16.dp, vertical = 8.dp),
        colors = CardDefaults.cardColors(containerColor = MaterialTheme.colorScheme.errorContainer)) {
        Column(Modifier.padding(12.dp)) {
            Text(text)
            TextButton(onClick = retry, enabled = !busy) { Text("Reintentar") }
        }
    }
}

@Composable
private fun OrderList(state: AdminState, model: AdminViewModel) {
    var search by remember { mutableStateOf(state.search) }
    Column {
        Row(Modifier.fillMaxWidth().padding(horizontal = 16.dp), verticalAlignment = Alignment.CenterVertically) {
            OutlinedTextField(search, { search = it.take(100) }, label = { Text("Código o cliente") },
                modifier = Modifier.weight(1f), singleLine = true, enabled = !state.busy, shape = RoundedCornerShape(16.dp))
            TextButton({ model.filter(state.status, search) }, enabled = !state.busy) { Text("Buscar") }
        }
        Row(Modifier.horizontalScroll(rememberScrollState()).padding(horizontal = 16.dp),
            horizontalArrangement = Arrangement.spacedBy(8.dp)) {
            statuses.forEach { (key, title) ->
                FilterChip(selected = key == state.status, onClick = { model.filter(key, search) },
                    enabled = !state.busy, label = { Text(title) })
            }
        }
        Row(Modifier.fillMaxWidth().padding(horizontal = 16.dp), verticalAlignment = Alignment.CenterVertically) {
            Text(state.orders?.let { "${it.total} pedidos" } ?: "Pedidos", Modifier.weight(1f))
            TextButton(model::refresh, enabled = !state.busy) { Text("Actualizar") }
        }
        LazyColumn(contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(12.dp)) {
            if (state.orders?.orders?.isEmpty() == true) item {
                Text("No hay pedidos con estos filtros.", Modifier.padding(vertical = 24.dp))
            }
            items(state.orders?.orders ?: emptyList(), key = { it.getLong("id") }) { order ->
                Card(onClick = { model.open(order.getLong("id")) }, enabled = !state.busy,
                    shape = RoundedCornerShape(20.dp), colors = CardDefaults.cardColors(containerColor = Color.White)) {
                    Column(Modifier.fillMaxWidth().padding(18.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
                        Row {
                            Text(order.text("code"), Modifier.weight(1f), fontWeight = FontWeight.Bold)
                            Text(money(order.text("total")), fontWeight = FontWeight.Bold)
                        }
                        Text(order.text("customer_name"))
                        Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                            Text(statuses[order.text("status")] ?: order.text("status"), color = MaterialTheme.colorScheme.primary)
                            Text(date(order.text("created_at")), style = MaterialTheme.typography.bodySmall)
                        }
                        Text(payment(order), style = MaterialTheme.typography.labelMedium)
                    }
                }
            }
            state.orders?.let { page ->
                item {
                    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween,
                        verticalAlignment = Alignment.CenterVertically) {
                        TextButton({ model.page(page.page - 1) }, enabled = !state.busy && page.page > 1) { Text("Anterior") }
                        Text("${page.page} / ${page.lastPage}")
                        TextButton({ model.page(page.page + 1) }, enabled = !state.busy && page.page < page.lastPage) { Text("Siguiente") }
                    }
                }
            }
        }
    }
}

@Composable
private fun Detail(state: AdminState, model: AdminViewModel) {
    LazyColumn(contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
        item {
            Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) {
                TextButton(model::back, enabled = !state.busy) { Text("‹ Pedidos") }
                TextButton(model::refresh, enabled = !state.busy) { Text("Actualizar") }
            }
        }
        val order = state.detail ?: return@LazyColumn
        item {
            Text(order.text("code"), style = MaterialTheme.typography.headlineMedium, fontWeight = FontWeight.Bold)
            Text(date(order.text("created_at")))
            Text(statuses[order.text("status")] ?: order.text("status"), color = MaterialTheme.colorScheme.primary)
        }
        item {
            Panel("Cliente y entrega") {
                val customer = order.optJSONObject("customer") ?: JSONObject()
                val delivery = order.optJSONObject("delivery") ?: JSONObject()
                Text(customer.text("name"), fontWeight = FontWeight.Bold)
                OptionalText(customer.text("phone"))
                OptionalText(customer.text("email"))
                OptionalText(delivery.text("address"))
                OptionalText(delivery.text("reference"))
                delivery.optJSONObject("driver")?.let { Text("Repartidor: " + it.text("name")) }
                OptionalText(order.text("customer_notes"))
            }
        }
        items(order.rows("items"), key = { it.getLong("id") }) { item ->
            Panel(item.text("quantity") + " × " + item.text("name")) {
                item.rows("extras").forEach { extra -> Text("+ " + extra.text("quantity") + " × " + extra.text("name")) }
                item.rows("combo_components").forEach { part ->
                    Text("• " + part.text("selected_quantity") + " × " + part.text("name"))
                }
                OptionalText(item.text("notes"))
                Text(money(item.text("line_total")), fontWeight = FontWeight.Bold)
            }
        }
        item {
            Panel("Pago") {
                Text(payment(order), fontWeight = FontWeight.Bold)
                Text(when (order.text("payment_method")) { "cash" -> "Efectivo"; "yape" -> "Yape"; "plin" -> "Plin"; else -> "Otro" })
                Amount("Productos", order.text("subtotal"))
                Amount("Delivery", order.text("delivery_fee"))
                Amount("Descuento", order.text("discount_amount"))
                HorizontalDivider()
                Amount("Total", order.text("total"))
                if (order.optBoolean("has_payment_receipt")) Text("Comprobante registrado", style = MaterialTheme.typography.bodySmall)
            }
        }
    }
}

@Composable
private fun Panel(title: String, content: @Composable ColumnScope.() -> Unit) {
    Card(Modifier.fillMaxWidth(), shape = RoundedCornerShape(20.dp),
        colors = CardDefaults.cardColors(containerColor = Color.White)) {
        Column(Modifier.padding(18.dp), verticalArrangement = Arrangement.spacedBy(8.dp)) {
            Text(title, style = MaterialTheme.typography.titleMedium, fontWeight = FontWeight.Bold)
            content()
        }
    }
}
@Composable private fun OptionalText(value: String) { if (value.isNotBlank()) Text(value) }
@Composable private fun Amount(label: String, value: String) {
    Row(Modifier.fillMaxWidth(), horizontalArrangement = Arrangement.SpaceBetween) { Text(label); Text(money(value)) }
}
