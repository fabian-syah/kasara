<?php

namespace App\Http\Controllers;

use App\Models\Question;
use Illuminate\Http\Request;

class QuestionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Question::orderBy('created_at', 'desc')->get();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'category' => 'required|string',
            'content' => 'required|string',
        ]);

        $question = Question::create($validated);

        return response()->json($question, 201);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Question $question)
    {
        $validated = $request->validate([
            'category' => 'required|string',
            'content' => 'required|string',
        ]);

        // Klien ingin pertanyaan lama tetap ada secara historis, tapi tidak aktif untuk transaksi baru.
        // Hasil edit menjadi pertanyaan baru yang aktif mulai saat ini.
        $question->delete(); // Soft delete old question
        $newQuestion = Question::create($validated);

        return response()->json($newQuestion);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Question $question)
    {
        $question->delete();

        return response()->json(null, 204);
    }
}
