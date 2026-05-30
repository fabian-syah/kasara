<?php

namespace App\Http\Controllers;

use App\Models\BranchLeague;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BranchLeagueController extends Controller
{
    /**
     * Get all league assignments for a given month/year
     */
    public function index(Request $request)
    {
        $month = $request->query('month', now()->month);
        $year = $request->query('year', now()->year);

        $assignments = BranchLeague::with('branch')
            ->forPeriod($month, $year)
            ->get()
            ->groupBy('league');

        $branches = Branch::where('is_active', true)
            ->where('type', 'physical')
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        // Get assigned branch IDs for this period
        $assignedIds = BranchLeague::forPeriod($month, $year)->pluck('branch_id')->toArray();

        // Unassigned branches
        $unassigned = $branches->filter(fn($b) => !in_array($b->id, $assignedIds));

        return response()->json([
            'success' => true,
            'data' => [
                'assignments' => $assignments,
                'branches' => $branches,
                'unassigned' => $unassigned->values(),
                'month' => (int) $month,
                'year' => (int) $year,
            ]
        ]);
    }

    /**
     * Assign a branch to a league for a given month/year
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'league' => 'required|in:liga_1,liga_2,zona_merah,non_liga',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2024|max:2030',
            'notes' => 'nullable|string|max:255',
        ]);

        $assignment = BranchLeague::updateOrCreate(
            [
                'branch_id' => $validated['branch_id'],
                'month' => $validated['month'],
                'year' => $validated['year'],
            ],
            [
                'league' => $validated['league'],
                'notes' => $validated['notes'] ?? null,
                'assigned_by' => Auth::id(),
            ]
        );

        return response()->json([
            'success' => true,
            'data' => $assignment->load('branch'),
            'message' => 'Liga berhasil diatur.'
        ]);
    }

    /**
     * Bulk assign branches to a league
     */
    public function bulkAssign(Request $request)
    {
        $validated = $request->validate([
            'branch_ids' => 'required|array|min:1',
            'branch_ids.*' => 'exists:branches,id',
            'league' => 'required|in:liga_1,liga_2,zona_merah,non_liga',
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2024|max:2030',
        ]);

        $userId = Auth::id();
        $count = 0;

        foreach ($validated['branch_ids'] as $branchId) {
            BranchLeague::updateOrCreate(
                [
                    'branch_id' => $branchId,
                    'month' => $validated['month'],
                    'year' => $validated['year'],
                ],
                [
                    'league' => $validated['league'],
                    'assigned_by' => $userId,
                ]
            );
            $count++;
        }

        return response()->json([
            'success' => true,
            'message' => "$count cabang berhasil dipindahkan ke " . BranchLeague::LEAGUES[$validated['league']] . "."
        ]);
    }

    /**
     * Remove a branch from any league for a given month/year
     */
    public function destroy(Request $request, $id)
    {
        $assignment = BranchLeague::findOrFail($id);
        $assignment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cabang berhasil dihapus dari liga.'
        ]);
    }

    /**
     * Copy assignments from one month to another
     */
    public function copyFromPrevious(Request $request)
    {
        $validated = $request->validate([
            'from_month' => 'required|integer|min:1|max:12',
            'from_year' => 'required|integer',
            'to_month' => 'required|integer|min:1|max:12',
            'to_year' => 'required|integer',
        ]);

        $source = BranchLeague::forPeriod($validated['from_month'], $validated['from_year'])->get();

        if ($source->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Tidak ada data di bulan sumber.'], 422);
        }

        $userId = Auth::id();
        $count = 0;

        foreach ($source as $item) {
            BranchLeague::updateOrCreate(
                [
                    'branch_id' => $item->branch_id,
                    'month' => $validated['to_month'],
                    'year' => $validated['to_year'],
                ],
                [
                    'league' => $item->league,
                    'notes' => $item->notes,
                    'assigned_by' => $userId,
                ]
            );
            $count++;
        }

        return response()->json([
            'success' => true,
            'message' => "$count assignment berhasil disalin."
        ]);
    }
}
