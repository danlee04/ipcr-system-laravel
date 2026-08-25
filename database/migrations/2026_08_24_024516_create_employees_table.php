<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employees - ang HR record ng bawat tao.
 * Hiwalay ito sa `users` (login credentials lang ang laman nun).
 *
 * Placement rules:
 *   - Rank & file / Section Head  -> section_id (galing sa section ang division)
 *   - Division Head               -> division_id lang (walang section)
 *   - Chief of Hospital           -> walang section at division, is_chief_of_hospital = true
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();

            // Login account. Nullable kasi pwedeng may employee record muna bago bigyan ng account.
            $table->foreignId('user_id')->nullable()->unique()
                ->constrained()->nullOnDelete();

            $table->string('employee_number', 50)->unique();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('suffix', 20)->nullable();          // Jr., III

            // ISANG plantilla position lang (pinanggagalingan ng CORE functions)
            $table->foreignId('position_id')->nullable()
                ->constrained()->nullOnDelete();

            $table->foreignId('section_id')->nullable()
                ->constrained()->nullOnDelete();
            $table->foreignId('division_id')->nullable()
                ->constrained()->nullOnDelete();

            $table->boolean('is_chief_of_hospital')->default(false);

            $table->date('date_hired')->nullable();
            $table->string('employment_status', 30)->default('permanent'); // permanent|casual|contractual|job_order|coterminous
            $table->boolean('is_active')->default(true);

            $table->timestamps();
            $table->softDeletes();   // huwag hard-delete: may naka-kabit na IPCR history

            $table->index(['last_name', 'first_name']);
            $table->index('is_active');
            $table->index('is_chief_of_hospital');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
