<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Question;

class AuditQuestionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $questions = [
            // Barang Masuk Inventory (Manual)
            [
                'category' => 'Barang Masuk Inventory',
                'content' => 'Apakah jumlah fisik barang sesuai dengan yang diinput di sistem?',
            ],
            [
                'category' => 'Barang Masuk Inventory',
                'content' => 'Apakah kondisi fisik barang dalam keadaan baik (tidak cacat)?',
            ],
            [
                'category' => 'Barang Masuk Inventory',
                'content' => 'Apakah label harga atau barcode sudah tertempel dengan benar?',
            ],
            [
                'category' => 'Barang Masuk Inventory',
                'content' => 'Apakah data supplier/sumber barang sudah benar?',
            ],

            // Pindah Cabang (Transfer)
            [
                'category' => 'pindah_cabang',
                'content' => 'Apakah nomor nota transfer sesuai dengan yang diterima?',
            ],
            [
                'category' => 'pindah_cabang',
                'content' => 'Apakah jumlah barang yang diterima sesuai dengan nota transfer?',
            ],
            [
                'category' => 'pindah_cabang',
                'content' => 'Apakah kondisi packaging barang aman saat diterima?',
            ],
            [
                'category' => 'pindah_cabang',
                'content' => 'Apakah ada selisih barang? Jika ya, apakah sudah dilaporkan?',
            ],
        ];

        foreach ($questions as $q) {
            Question::updateOrCreate(
                ['category' => $q['category'], 'content' => $q['content']],
                $q
            );
        }
    }
}
