<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ModificationRequest;
use App\Models\Owner;
use App\Models\Parcel;
use Illuminate\Database\Seeder;

class ModificationRequestsSeeder extends Seeder
{
    public function run(): void
    {
        $parcelIds = Parcel::pluck('id')->toArray();
        $ownerId = Owner::value('id');

        if (empty($parcelIds) || $ownerId === null) {
            $this->command->warn('No parcels or owners found — skipping ModificationRequestsSeeder.');

            return;
        }

        $samples = [
            // pending
            ['field_name' => 'land_transaction', 'old_value' => null,    'new_value' => 'مباعة',   'status' => 'pending', 'days_ago' => 2],
            ['field_name' => 'asset_type',        'old_value' => 'أرض',   'new_value' => 'شقة',     'status' => 'pending', 'days_ago' => 5],
            ['field_name' => 'allocation_method', 'old_value' => null,    'new_value' => 'منح',     'status' => 'pending', 'days_ago' => 1],
            ['field_name' => 'land_transaction',  'old_value' => 'خاصة',  'new_value' => 'قيد البيع', 'status' => 'pending', 'days_ago' => 3],
            ['field_name' => 'allocation_method', 'old_value' => null,    'new_value' => 'شراء',    'status' => 'pending', 'days_ago' => 7],

            // sent_to_arcgis
            ['field_name' => 'deed_area',         'old_value' => '25000', 'new_value' => '25500',   'status' => 'sent_to_arcgis', 'notes' => 'خطأ مساحي في الصك الأصلي', 'days_ago' => 10],
            ['field_name' => 'land_transaction',  'old_value' => 'خاصة',  'new_value' => 'مؤجرة',  'status' => 'sent_to_arcgis', 'days_ago' => 8],

            // applied
            ['field_name' => 'fall_in',           'old_value' => null,    'new_value' => 'حي النزهة', 'status' => 'applied', 'notes' => 'تأكّد من خريطة البلدية', 'days_ago' => 14, 'resolved_days_ago' => 3],

            // rejected
            ['field_name' => 'asset_type',        'old_value' => 'أرض',   'new_value' => 'مستودع', 'status' => 'rejected', 'notes' => 'التصنيف لا يتطابق مع نوع الصك', 'days_ago' => 20, 'resolved_days_ago' => 7],
            ['field_name' => 'deed_area',         'old_value' => '33535', 'new_value' => '34000',   'status' => 'rejected', 'notes' => 'الفرق يتجاوز هامش الخطأ المسموح', 'days_ago' => 18, 'resolved_days_ago' => 5],
        ];

        foreach ($samples as $i => $s) {
            $resolvedAt = isset($s['resolved_days_ago'])
                ? now()->subDays($s['resolved_days_ago'])
                : null;

            ModificationRequest::create([
                'parcel_id' => $parcelIds[$i % count($parcelIds)],
                'requested_by' => $ownerId,
                'field_name' => $s['field_name'],
                'old_value' => $s['old_value'],
                'new_value' => $s['new_value'],
                'status' => $s['status'],
                'notes' => $s['notes'] ?? null,
                'resolved_at' => $resolvedAt,
                'created_at' => now()->subDays($s['days_ago']),
                'updated_at' => now()->subDays($s['days_ago']),
            ]);
        }

        $this->command->info('ModificationRequests: '.count($samples).' sample requests created.');
    }
}
