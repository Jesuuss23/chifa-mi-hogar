<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('reviews');
    }

    public function down(): void
    {
        // La tabla eliminada no se restaura automáticamente.
        // Su estructura original permanece en la migración de creación.
    }
};