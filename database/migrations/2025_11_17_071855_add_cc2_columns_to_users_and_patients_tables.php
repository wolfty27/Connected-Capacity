<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCc2ColumnsToUsersAndPatientsTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('users')) {
            $missingOrgId = !Schema::hasColumn('users', 'organization_id');
            $missingOrgRole = !Schema::hasColumn('users', 'organization_role');

            if ($missingOrgId || $missingOrgRole) {
                Schema::table('users', function (Blueprint $table) use ($missingOrgId, $missingOrgRole) {
                    if ($missingOrgId) {
                        $table->foreignId('organization_id')->nullable()->after('id')->constrained('service_provider_organizations')->nullOnDelete();
                    }

                    if ($missingOrgRole) {
                        $table->string('organization_role')->nullable()->after('role');
                    }

                    $table->index(['organization_id', 'organization_role'], 'users_org_role_idx');
                });
            }
        }

        if (Schema::hasTable('patients')) {
            Schema::table('patients', function (Blueprint $table) {
                if (!Schema::hasColumn('patients', 'triage_summary')) {
                    $table->json('triage_summary')->nullable()->after('status');
                }

                if (!Schema::hasColumn('patients', 'maple_score')) {
                    $table->string('maple_score')->nullable()->after('triage_summary');
                }

                if (!Schema::hasColumn('patients', 'rai_cha_score')) {
                    $table->string('rai_cha_score')->nullable()->after('maple_score');
                }

                if (!Schema::hasColumn('patients', 'risk_flags')) {
                    $table->json('risk_flags')->nullable()->after('rai_cha_score');
                }

                if (!Schema::hasColumn('patients', 'primary_coordinator_id')) {
                    $table->foreignId('primary_coordinator_id')->nullable()->after('risk_flags')->constrained('users')->nullOnDelete();
                }

                if (!Schema::hasColumn('patients', 'is_in_queue')) {
                    $table->boolean('is_in_queue')->default(false)->after('primary_coordinator_id');
                }

                if (!Schema::hasColumn('patients', 'activated_at')) {
                    $table->timestamp('activated_at')->nullable()->after('is_in_queue');
                }

                if (!Schema::hasColumn('patients', 'activated_by')) {
                    $table->foreignId('activated_by')->nullable()->after('activated_at')->constrained('users')->nullOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (Schema::hasTable('patients')) {
            Schema::table('patients', function (Blueprint $table) {
                if (Schema::hasColumn('patients', 'primary_coordinator_id')) {
                    $table->dropForeign(['primary_coordinator_id']);
                    $table->dropColumn('primary_coordinator_id');
                }

                if (Schema::hasColumn('patients', 'activated_by')) {
                    $table->dropForeign(['activated_by']);
                    $table->dropColumn('activated_by');
                }

                foreach (['activated_at', 'is_in_queue', 'risk_flags', 'rai_cha_score', 'maple_score', 'triage_summary'] as $column) {
                    if (Schema::hasColumn('patients', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'organization_role')) {
                    $table->dropColumn('organization_role');
                }

                if (Schema::hasColumn('users', 'organization_id')) {
                    $table->dropForeign(['organization_id']);
                    $table->dropColumn('organization_id');
                }
            });
        }
    }
}
