<?php

namespace App\Http\Controllers\Setting;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreHolidayRuleRequest;
use App\Http\Requests\UpdateHolidayRuleRequest;
use App\Models\Branch;
use App\Models\HolidayRule;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class HolidayController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:ver_festivos', only: ['index']),
            new Middleware('can:crear_festivos', only: ['store']),
            new Middleware('can:editar_festivos', only: ['update']),
            new Middleware('can:eliminar_festivos', only: ['destroy']),
        ];
    }

    public function index(Request $request)
    {
        $holidays = HolidayRule::with('branches:id,name')
            ->when($request->input('search'), fn ($q, $search) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Setting/Holiday/Index', [
            'holidays' => $holidays,
            'filters' => $request->only(['search']),
            'branches' => Branch::all(['id', 'name']),
        ]);
    }

    public function store(StoreHolidayRuleRequest $request)
    {
        $validated = $request->validated();

        $holiday = HolidayRule::create([
            'name' => $validated['name'],
            'rule_definition' => $validated['rule_definition'],
            'is_active' => $validated['is_active'],
        ]);

        if (!$validated['applies_to_all']) {
            $holiday->branches()->sync($validated['branch_ids']);
        }

        return back()->with('success', 'Día festivo registrado.');
    }

    public function update(UpdateHolidayRuleRequest $request, HolidayRule $holiday)
    {
        $validated = $request->validated();

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

        return back()->with('success', 'Día festivo actualizado.');
    }

    public function destroy(HolidayRule $holiday)
    {
        $holiday->delete();
        return back()->with('success', 'Día festivo eliminado.');
    }
}