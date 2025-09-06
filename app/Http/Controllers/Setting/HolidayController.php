<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\HolidayRule;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class HolidayController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:ver_festivos', only: ['index', 'show']),
            new Middleware('can:crear_festivos', only: ['store']),
            new Middleware('can:editar_festivos', only: ['update']),
            new Middleware('can:eliminar_festivos', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $query = HolidayRule::with('branches:id,name');

        $query->when($request->input('search'), function ($q, $search) {
            $q->where('name', 'like', "%{$search}%");
        });

        return Inertia::render('Setting/Holiday/Index', [
            'holidays' => $query->orderBy('id')->paginate(20)->withQueryString(),
            'filters' => $request->only(['search']),
            'branches' => Branch::all(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:holiday_rules,name',
            'is_custom' => 'required|boolean',
            'rule_definition' => 'required|array',
            'applies_to_all' => 'required|boolean',
            'branch_ids' => 'required_if:applies_to_all,false|array',
            'branch_ids.*' => 'exists:branches,id',
            'is_active' => 'required|boolean',
        ]);
        
        $holiday = HolidayRule::create([
            'name' => $validated['name'],
            'rule_definition' => $validated['rule_definition'],
            'is_active' => $validated['is_active'],
        ]);

        if (!$validated['applies_to_all']) {
            $holiday->branches()->sync($validated['branch_ids']);
        }

        return back()->with('success', 'Dia festivo registrado.');
    }

    public function update(Request $request, HolidayRule $holiday)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('holiday_rules')->ignore($holiday->id)],
            'is_custom' => 'required|boolean',
            'rule_definition' => 'required|array',
            'applies_to_all' => 'required|boolean',
            'branch_ids' => 'required_if:applies_to_all,false|array',
            'branch_ids.*' => 'exists:branches,id',
            'is_active' => 'required|boolean',
        ]);

        $holiday->update([
            'name' => $validated['name'],
            'rule_definition' => $validated['rule_definition'],
            'is_active' => $validated['is_active'],
        ]);

        if ($validated['applies_to_all']) {
            $holiday->branches()->detach();
        } else {
            $holiday->branches()->sync($validated['branch_ids']);
        }

        return back()->with('success', 'Dia festivo actualizado.');
    }

    public function destroy(HolidayRule $holiday)
    {
        $holiday->delete();
        return back()->with('success', 'Dia festivo eliminado.');
    }
}
