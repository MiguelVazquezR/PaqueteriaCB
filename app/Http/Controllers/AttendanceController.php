<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Services\RekognitionService;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    /**
     * Handles clock-in and clock-out actions.
     */
    public function store(Request $request, RekognitionService $rekognitionService)
    {
        $employee = $this->getEmployeeFromImage($request, $rekognitionService);
        if (!$employee) {
            return back()->with('error', 'Rostro no reconocido o empleado no encontrado.');
        }

        $lastAttendance = $employee->attendances()->whereDate('created_at', today())->latest()->first();

        // Si no hay fichajes hoy, es una entrada.
        if (!$lastAttendance) {
            return $this->createAttendance($employee, 'entry');
        }

        // Si el último fichaje fue una salida, no se permiten más.
        if ($lastAttendance->type === 'exit') {
            return back()->with('error', 'Ya has registrado tu salida por hoy.');
        }

        // En cualquier otro caso (entry, break_start, break_end), se registra la salida.
        return $this->createAttendance($employee, 'exit');
    }

    /**
     * Handles break start and end actions.
     */
    public function storeBreak(Request $request, RekognitionService $rekognitionService)
    {
        $employee = $this->getEmployeeFromImage($request, $rekognitionService);
        if (!$employee) {
            return back()->with('error', 'Rostro no reconocido o empleado no encontrado.');
        }

        $lastAttendance = $employee->attendances()->whereDate('created_at', today())->latest()->first();

        // No se puede tomar un descanso si no se ha registrado la entrada o si ya se salió.
        if (!$lastAttendance || in_array($lastAttendance->type, ['exit'])) {
            return back()->with('error', 'Debes registrar tu entrada antes de tomar un descanso.');
        }

        // Determinar si es inicio o fin de descanso.
        $nextBreakType = ($lastAttendance->type === 'break_start') ? 'break_end' : 'break_start';

        return $this->createAttendance($employee, $nextBreakType);
    }

    private function getEmployeeFromImage(Request $request, RekognitionService $rekognitionService): ?Employee
    {
        $request->validate(['image' => 'required|string']);
        $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $request->input('image')));
        $faceId = $rekognitionService->searchFacesByImage($imageData);

        return $faceId ? Employee::where('aws_rekognition_face_id', $faceId)->first() : null;
    }

    private function createAttendance(Employee $employee, string $type)
    {
        Attendance::create(['employee_id' => $employee->id, 'type' => $type, 'created_at' => now()]);
        $translatedType = match ($type) {
            'entry' => 'Entrada', 'break_start' => 'Inicio de descanso',
            'break_end' => 'Fin de descanso', 'exit' => 'Salida', default => 'Fichaje',
        };
        return back()->with('success', "¡Hola, {$employee->first_name}! Se registró tu: {$translatedType}.");
    }
}
