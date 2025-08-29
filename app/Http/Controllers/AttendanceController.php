<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Services\RekognitionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class AttendanceController extends Controller
{
    // --- MÉTODOS PARA USUARIOS AUTENTICADOS (Sin cambios) ---

    public function store(Request $request, RekognitionService $rekognitionService)
    {
        $employee = $this->getEmployeeFromImage($request, $rekognitionService);
        if (!$employee) {
            if (session('error_mismatch')) {
                return back()->with('error', 'El rostro detectado no coincide con tu usuario. Fichaje denegado.');
            }
            return back()->with('error', 'Rostro no reconocido. Por favor, inténtalo de nuevo en un lugar bien iluminado.');
        }
        $lastAttendance = $employee->attendances()->whereDate('created_at', today())->latest()->first();
        if (!$lastAttendance || in_array($lastAttendance->type, ['exit', 'break_start'])) {
             return $this->createAttendance($employee, ($lastAttendance && $lastAttendance->type === 'break_start') ? 'exit' : 'entry');
        }
        return $this->createAttendance($employee, 'exit');
    }
    
    public function storeBreak(Request $request, RekognitionService $rekognitionService)
    {
        $employee = $this->getEmployeeFromImage($request, $rekognitionService);
        if (!$employee) {
            if (session('error_mismatch')) {
                return back()->with('error', 'El rostro detectado no coincide con tu usuario. Fichaje denegado.');
            }
            return back()->with('error', 'Rostro no reconocido o empleado no encontrado.');
        }
        $lastAttendance = $employee->attendances()->whereDate('created_at', today())->latest()->first();
        if (!$lastAttendance || in_array($lastAttendance->type, ['exit'])) {
            return back()->with('error', 'Debes registrar tu entrada antes de tomar un descanso.');
        }
        $nextBreakType = ($lastAttendance->type === 'break_start') ? 'break_end' : 'break_start';
        return $this->createAttendance($employee, $nextBreakType);
    }

    // --- MÉTODOS PARA EL MODO QUIOSCO ---

    public function kiosk()
    {
        return Inertia::render('Attendance/Kiosk');
    }

    public function kioskStore(Request $request, RekognitionService $rekognitionService)
    {
        $request->validate(['image' => 'required|string']);
        $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $request->input('image')));
        $employee = $this->findEmployeeByFace($imageData, $rekognitionService);
        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Rostro no reconocido. Inténtalo de nuevo.'], 404);
        }
        $lastAttendance = $employee->attendances()->whereDate('created_at', today())->latest()->first();
        $type = 'entry';
        if ($lastAttendance) {
            if ($lastAttendance->type === 'exit') {
                return response()->json(['success' => false, 'message' => "{$employee->first_name}, tu jornada ya ha finalizado por hoy."], 400);
            }
            if (in_array($lastAttendance->type, ['entry', 'break_end'])) {
                $type = 'exit';
            }
        }
        Attendance::create(['employee_id' => $employee->id, 'type' => $type, 'created_at' => now()]);
        $translatedType = $type === 'entry' ? 'Entrada' : 'Salida';
        return response()->json(['success' => true, 'message' => "¡Hola, {$employee->first_name}! Se registró tu {$translatedType}."]);
    }

    /**
     * Procesa un fichaje de descanso desde el modo quiosco.
     */
    public function kioskStoreBreak(Request $request, RekognitionService $rekognitionService)
    {
        $request->validate(['image' => 'required|string']);
        $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $request->input('image')));

        $employee = $this->findEmployeeByFace($imageData, $rekognitionService);

        if (!$employee) {
            return response()->json(['success' => false, 'message' => 'Rostro no reconocido.'], 404);
        }

        $lastAttendance = $employee->attendances()->whereDate('created_at', today())->latest()->first();

        // No se puede tomar un descanso si no ha entrado o si ya salió.
        if (!$lastAttendance || in_array($lastAttendance->type, ['exit'])) {
            return response()->json(['success' => false, 'message' => 'Debes registrar tu entrada antes de tomar un descanso.'], 400);
        }

        // Determinar si es inicio o fin de descanso.
        $type = ($lastAttendance->type === 'break_start') ? 'break_end' : 'break_start';

        Attendance::create(['employee_id' => $employee->id, 'type' => $type, 'created_at' => now()]);
        
        $translatedType = $type === 'break_start' ? 'Inicio de descanso' : 'Fin de descanso';

        return response()->json([
            'success' => true,
            'message' => "{$employee->first_name}, se registró tu {$translatedType}."
        ]);
    }

    // --- MÉTODOS PRIVADOS AUXILIARES (Sin cambios) ---

    private function getEmployeeFromImage(Request $request, RekognitionService $rekognitionService): ?Employee
    {
        $request->validate(['image' => 'required|string']);
        if (!Auth::check() || !($loggedInEmployee = Auth::user()->employee)) {
            Log::warning('Intento de fichaje por un usuario no autenticado o sin perfil de empleado.');
            return null;
        }
        $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $request->input('image')));
        $foundEmployee = $this->findEmployeeByFace($imageData, $rekognitionService);
        if (!$foundEmployee) {
            return null;
        }
        if ($foundEmployee->id !== $loggedInEmployee->id) {
            Log::warning("DISCREPANCIA DE FICHAJE: Rostro (Empleado ID: {$foundEmployee->id}) no coincide con usuario autenticado (Empleado ID: {$loggedInEmployee->id}).");
            session()->flash('error_mismatch', true);
            return null;
        }
        return $foundEmployee;
    }

    private function findEmployeeByFace(string $imageData, RekognitionService $rekognitionService): ?Employee
    {
        $faceId = $rekognitionService->searchFaceByImage($imageData);
        if (!$faceId) {
            Log::info('Búsqueda facial en quiosco: No se encontró coincidencia en Rekognition.');
            return null;
        }
        $employee = Employee::where('aws_rekognition_face_id', $faceId)->first();
        if (!$employee) {
            Log::warning("Rostro encontrado en Rekognition (FaceID: {$faceId}) pero no está asignado a ningún empleado.");
        }
        return $employee;
    }

    private function createAttendance(Employee $employee, string $type)
    {
        Attendance::create(['employee_id' => $employee->id, 'type' => $type, 'created_at' => now()]);
        $translatedType = match ($type) {
            'entry' => 'Entrada', 'break_start' => 'Inicio de descanso',
            'break_end' => 'Fin de descanso', 'exit' => 'Salida', default => 'Fichaje',
        };
        return back()->with('success', "¡Hola, {$employee->first_name}! Se registró tu {$translatedType} correctamente.");
    }
}