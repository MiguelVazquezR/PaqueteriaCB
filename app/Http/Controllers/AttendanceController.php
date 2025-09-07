<?php

namespace App\Http\Controllers;

use App\Http\Requests\AttendanceRequest;
use App\Services\AttendanceService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AttendanceController extends Controller
{
    public function __construct(protected AttendanceService $attendanceService)
    {
    }

    // --- MÉTODOS PARA USUARIOS AUTENTICADOS ---

    public function store(AttendanceRequest $request)
    {
        return $this->handleAuthenticatedAttendance($request, 'work');
    }

    public function storeBreak(AttendanceRequest $request)
    {
        return $this->handleAuthenticatedAttendance($request, 'break');
    }

    private function handleAuthenticatedAttendance(AttendanceRequest $request, string $mode)
    {
        $imageData = $this->decodeImage($request->validated('image'));
        $authEmployee = Auth::user()?->employee;

        if (!$authEmployee) {
            return back()->with('error', 'Tu usuario no está asociado a un perfil de empleado.');
        }

        $result = $this->attendanceService->recordAttendanceFromImage($imageData, $mode, $authEmployee);

        $flashType = $result['success'] ? 'success' : 'error';

        return back()->with($flashType, $result['message']);
    }

    // --- MÉTODOS PARA EL MODO QUIOSCO ---

    public function kiosk()
    {
        return Inertia::render('Attendance/Kiosk');
    }

    public function kioskStore(AttendanceRequest $request)
    {
        return $this->handleKioskAttendance($request, 'work');
    }

    public function kioskStoreBreak(AttendanceRequest $request)
    {
        return $this->handleKioskAttendance($request, 'break');
    }

    private function handleKioskAttendance(AttendanceRequest $request, string $mode)
    {
        $imageData = $this->decodeImage($request->validated('image'));
        $result = $this->attendanceService->recordAttendanceFromImage($imageData, $mode);

        return response()->json($result, $result['status'] ?? 200);
    }

    // --- MÉTODO PRIVADO AUXILIAR ---
    
    private function decodeImage(string $base64Image): string
    {
        return base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64Image));
    }
}