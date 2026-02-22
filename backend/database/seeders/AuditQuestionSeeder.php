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
                'category' => 'barang_masuk',
                'content' => 'Apakah jumlah fisik barang sesuai dengan yang diinput di sistem?',
            ],
            [
                'category' => 'barang_masuk',
                'content' => 'Apakah kondisi fisik barang dalam keadaan baik (tidak cacat)?',
            ],
            [
                'category' => 'barang_masuk',
                'content' => 'Apakah label harga atau barcode sudah tertempel dengan benar?',
            ],
            [
                'category' => 'barang_masuk',
                'content' => 'Apakah data supplier/sumber barang sudah benar?',
            ],

            // Pindah Cabang (Transfer Incoming)
            [
                'category' => 'pindah_cabang_masuk',
                'content' => 'Apakah nomor nota transfer sesuai dengan yang diterima?',
            ],
            [
                'category' => 'pindah_cabang_masuk',
                'content' => 'Apakah jumlah barang yang diterima sesuai dengan nota transfer?',
            ],
            [
                'category' => 'pindah_cabang_masuk',
                'content' => 'Apakah kondisi packaging barang aman saat diterima?',
            ],
            [
                'category' => 'pindah_cabang_masuk',
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
