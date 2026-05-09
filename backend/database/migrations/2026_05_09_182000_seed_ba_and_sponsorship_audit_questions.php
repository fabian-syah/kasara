<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\Question;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $questions = [
            // Brand Ambassador
            [
                'category' => 'brand_ambassador',
                'content' => 'Apakah nama Brand Ambassador dan akun sosial medianya sudah tervalidasi benar?',
            ],
            [
                'category' => 'brand_ambassador',
                'content' => 'Apakah sudah dilampirkan bukti penyerahan barang / dokumentasi dengan Brand Ambassador?',
            ],
            [
                'category' => 'brand_ambassador',
                'content' => 'Apakah MoU atau kontrak kerjasama dengan Brand Ambassador sudah lengkap dan ditandatangani?',
            ],
            [
                'category' => 'brand_ambassador',
                'content' => 'Apakah barang yang dikeluarkan sudah sesuai dengan tipe yang tercantum dalam kesepakatan?',
            ],

            // Event / Sponsorship
            [
                'category' => 'event_sponsorship',
                'content' => 'Apakah proposal atau dokumen event/sponsorship resmi sudah dilampirkan dan divalidasi?',
            ],
            [
                'category' => 'event_sponsorship',
                'content' => 'Apakah pihak penanggung jawab/penerima sponsorship sudah sesuai dengan data di proposal?',
            ],
            [
                'category' => 'event_sponsorship',
                'content' => 'Apakah dokumentasi serah terima barang sponsorship sudah lengkap dan diunggah?',
            ],
            [
                'category' => 'event_sponsorship',
                'content' => 'Apakah benefit sponsorship (seperti pemasangan logo/ad-space) sudah terkonfirmasi dan terealisasi?',
            ],
        ];

        foreach ($questions as $q) {
            Question::updateOrCreate(
                ['category' => $q['category'], 'content' => $q['content']],
                $q
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Question::whereIn('category', ['brand_ambassador', 'event_sponsorship'])->delete();
    }
};
