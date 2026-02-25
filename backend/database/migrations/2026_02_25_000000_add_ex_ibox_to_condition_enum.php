<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE product_details DROP CONSTRAINT IF EXISTS product_details_condition_check");
            DB::statement("ALTER TABLE product_details ADD CONSTRAINT product_details_condition_check CHECK (condition::text = ANY (ARRAY['new'::character varying, 'second'::character varying, 'ex_ibox'::character varying]::text[]))");

            try {
                DB::statement("ALTER TABLE product_prices DROP CONSTRAINT IF EXISTS product_prices_condition_check");
                DB::statement("ALTER TABLE product_prices ADD CONSTRAINT product_prices_condition_check CHECK (condition::text = ANY (ARRAY['new'::character varying, 'second'::character varying, 'ex_ibox'::character varying]::text[]))");
            } catch (\Exception $e) {
            }
        } else {
            DB::statement("ALTER TABLE `product_details` MODIFY COLUMN `condition` ENUM('new', 'second', 'ex_ibox') NOT NULL DEFAULT 'new'");
            try {
                DB::statement("ALTER TABLE `product_prices` MODIFY COLUMN `condition` ENUM('new', 'second', 'ex_ibox') NOT NULL DEFAULT 'new'");
            } catch (\Exception $e) {
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE product_details DROP CONSTRAINT IF EXISTS product_details_condition_check");
            DB::statement("ALTER TABLE product_details ADD CONSTRAINT product_details_condition_check CHECK (condition::text = ANY (ARRAY['new'::character varying, 'second'::character varying]::text[]))");

            try {
                DB::statement("ALTER TABLE product_prices DROP CONSTRAINT IF EXISTS product_prices_condition_check");
                DB::statement("ALTER TABLE product_prices ADD CONSTRAINT product_prices_condition_check CHECK (condition::text = ANY (ARRAY['new'::character varying, 'second'::character varying]::text[]))");
            } catch (\Exception $e) {
            }
        } else {
            DB::statement("ALTER TABLE `product_details` MODIFY COLUMN `condition` ENUM('new', 'second') NOT NULL DEFAULT 'new'");
            try {
                DB::statement("ALTER TABLE `product_prices` MODIFY COLUMN `condition` ENUM('new', 'second') NOT NULL DEFAULT 'new'");
            } catch (\Exception $e) {
            }
        }
    }
};
