<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add Team Lane grouping metadata to StaffRole model
 * 
 * Team Lanes are a UI concept in Scheduler 2.0 that groups staff by role category
 * for visual organization. This migration adds fields to customize the grouping behavior.
 * 
 * Fields:
 * - team_lane_group: Which lane group this role belongs to (e.g., 'nursing', 'allied_health', 'psw')
 * - team_lane_label: Display label for the lane (e.g., 'Nursing Staff', 'Allied Health')
 * - team_lane_sort_order: Order within the scheduler grid (lower = higher)
 * - individual_lanes: Whether each staff member gets their own lane (true) or combines (false)
 * 
 * @see docs/CC21 Scheduler 2.0 prelim – Design & Implementation Spec.txt
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('staff_roles', function (Blueprint $table) {
            // Team Lane group identifier (e.g., 'nursing', 'allied_health', 'psw', 'admin')
            $table->string('team_lane_group', 50)->nullable()->after('category');
            
            // Display label for the team lane in UI
            $table->string('team_lane_label', 100)->nullable()->after('team_lane_group');
            
            // Sort order for displaying lanes (lower = displayed first)
            $table->integer('team_lane_sort_order')->default(100)->after('team_lane_label');
            
            // Whether staff with this role get individual lanes (true) or combine into group lane (false)
            // Default true for high-population roles like PSW, RPN
            $table->boolean('individual_lanes')->default(true)->after('team_lane_sort_order');
            
            // Index for efficient querying
            $table->index('team_lane_group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('staff_roles', function (Blueprint $table) {
            $table->dropIndex(['team_lane_group']);
            $table->dropColumn([
                'team_lane_group',
                'team_lane_label',
                'team_lane_sort_order',
                'individual_lanes',
            ]);
        });
    }
};
