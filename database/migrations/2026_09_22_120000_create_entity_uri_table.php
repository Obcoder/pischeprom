<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entity_uri', function (Blueprint $table): void {
            $table->foreignId('entity_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uri_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['entity_id', 'uri_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entity_uri');
    }
};
