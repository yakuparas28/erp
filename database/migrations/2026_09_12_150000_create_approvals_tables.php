<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_workflows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('subject_type', 100);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'subject_type', 'is_active'], 'idx_wf_tenant_subject');
        });

        Schema::create('approval_workflow_steps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('approval_workflow_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('approver_type', 40);
            $table->string('approver_value')->nullable();
            $table->timestamps();

            $table->unique(['approval_workflow_id', 'sequence'], 'uq_wf_step_seq');
        });

        Schema::create('approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('approvable_type', 100);
            $table->unsignedBigInteger('approvable_id');
            $table->foreignId('approval_workflow_id')->constrained();
            $table->unsignedInteger('current_step')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->foreignId('submitted_by')->constrained('users');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['approvable_type', 'approvable_id'], 'idx_approvals_subject');
            $table->index(['tenant_id', 'status'], 'idx_approvals_tenant_status');
        });

        Schema::create('approval_actions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('approval_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('step_sequence')->nullable();
            $table->foreignId('actor_user_id')->constrained('users');
            $table->enum('action', ['approve', 'reject', 'cancel']);
            $table->text('comment')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_actions');
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('approval_workflow_steps');
        Schema::dropIfExists('approval_workflows');
    }
};
