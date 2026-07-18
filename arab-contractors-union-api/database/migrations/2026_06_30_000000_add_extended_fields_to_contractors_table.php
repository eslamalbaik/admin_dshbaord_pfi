<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            // Text fields
            if (!Schema::hasColumn('contractors', 'partners'))
                $table->text('partners')->nullable()->after('name');
            if (!Schema::hasColumn('contractors', 'fax'))
                $table->string('fax')->nullable()->after('phone');
            if (!Schema::hasColumn('contractors', 'building'))
                $table->string('building')->nullable()->after('address');
            if (!Schema::hasColumn('contractors', 'floor'))
                $table->string('floor')->nullable()->after('building');
            if (!Schema::hasColumn('contractors', 'capital'))
                $table->string('capital')->nullable()->after('classification');
            if (!Schema::hasColumn('contractors', 'registration_date'))
                $table->date('registration_date')->nullable()->after('established_year');
            if (!Schema::hasColumn('contractors', 'legal_form'))
                $table->string('legal_form')->nullable()->after('registration_date');
            if (!Schema::hasColumn('contractors', 'company_purposes'))
                $table->text('company_purposes')->nullable()->after('legal_form');

            // File fields
            if (!Schema::hasColumn('contractors', 'authorized_signature'))
                $table->string('authorized_signature')->nullable()->after('notes');
            if (!Schema::hasColumn('contractors', 'lease_or_ownership_contract'))
                $table->string('lease_or_ownership_contract')->nullable()->after('authorized_signature');
            if (!Schema::hasColumn('contractors', 'company_approval_letter'))
                $table->string('company_approval_letter')->nullable()->after('lease_or_ownership_contract');
            if (!Schema::hasColumn('contractors', 'municipal_license'))
                $table->string('municipal_license')->nullable()->after('company_approval_letter');
            if (!Schema::hasColumn('contractors', 'company_register'))
                $table->string('company_register')->nullable()->after('municipal_license');
            if (!Schema::hasColumn('contractors', 'articles_of_association'))
                $table->string('articles_of_association')->nullable()->after('company_register');
            if (!Schema::hasColumn('contractors', 'internal_bylaws'))
                $table->string('internal_bylaws')->nullable()->after('articles_of_association');
            if (!Schema::hasColumn('contractors', 'bank_dealing_letter'))
                $table->string('bank_dealing_letter')->nullable()->after('internal_bylaws');
            if (!Schema::hasColumn('contractors', 'secretary_contract'))
                $table->string('secretary_contract')->nullable()->after('bank_dealing_letter');
            if (!Schema::hasColumn('contractors', 'full_time_engineer_certificate'))
                $table->string('full_time_engineer_certificate')->nullable()->after('secretary_contract');
            if (!Schema::hasColumn('contractors', 'partners_ids'))
                $table->string('partners_ids')->nullable()->after('full_time_engineer_certificate');
            if (!Schema::hasColumn('contractors', 'authorization_letter'))
                $table->string('authorization_letter')->nullable()->after('partners_ids');
        });
    }

    public function down(): void
    {
        Schema::table('contractors', function (Blueprint $table) {
            $table->dropColumn([
                'partners',
                'fax',
                'building',
                'floor',
                'capital',
                'registration_date',
                'legal_form',
                'company_purposes',
                'authorized_signature',
                'lease_or_ownership_contract',
                'company_approval_letter',
                'municipal_license',
                'company_register',
                'articles_of_association',
                'internal_bylaws',
                'bank_dealing_letter',
                'secretary_contract',
                'full_time_engineer_certificate',
                'partners_ids',
                'authorization_letter',
            ]);
        });
    }
};
