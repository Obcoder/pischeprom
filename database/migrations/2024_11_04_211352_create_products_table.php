<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('rus')->index();
            $table->string('eng')->nullable();
            $table->string('zh')->nullable();
            $table->string('es')->nullable();
            $table->string('ar')->nullable();
            $table->string('po')->nullable();
            $table->string('de')->nullable();
            $table->string('fr')->nullable();
            $table->string('hi')->nullable();
            $table->string('tu')->nullable();
            $table->string('vi')->nullable();
            $table->string('it')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
