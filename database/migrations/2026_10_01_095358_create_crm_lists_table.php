<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * CRM lists keep each product's prospects apart, so a campaign written
     * for one product is never sent to contacts gathered for another. A
     * list may belong to a researched product (offer_id) or stand alone;
     * a contact can sit on more than one list.
     */
    public function up(): void
    {
        Schema::create('crm_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('color', 20)->default('brand');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'offer_id']);
        });

        Schema::create('crm_contact_list', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_list_id')->constrained()->cascadeOnDelete();
            $table->timestamp('added_at')->useCurrent();

            $table->unique(['crm_contact_id', 'crm_list_id']);
            $table->index(['crm_list_id', 'added_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_contact_list');
        Schema::dropIfExists('crm_lists');
    }
};
