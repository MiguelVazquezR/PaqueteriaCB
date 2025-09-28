<?php

namespace App\Services;

use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Support\Facades\Log;
use App\Services\IncidentService;
use Throwable;

class AttendanceService
{
    public function __construct(protected RekognitionService $rekognitionService, private IncidentService $incidentService)
    {
    }

    /**
     * The main method to process an attendance record from an image.
     * Handles both kiosk (no auth) and authenticated user scenarios.
     *
     * @param string $imageData The raw binary image data.
     * @param string $attendanceMode 'work' for entry/exit, 'break' for breaks.
     * @param Employee|null $authEmployee The employee associated with the logged-in user, if any.
     *
     * @return array A result array with 'success', 'message', 'status' and 'employee' keys.
     */
    public function recordAttendanceFromImage(string $imageData, string $attendanceMode, ?Employee $authEmployee = null): array
    {
        try {
            $foundEmployee = $this->findEmployeeByFace($imageData);

            if (!$foundEmployee) {
                return ['success' => false, 'message' => 'Rostro no reconocido. Inténtalo de nuevo.', 'status' => 404];
            }

            // If an authenticated employee is provided, verify the face matches.
            if ($authEmployee && $authEmployee->id !== $foundEmployee->id) {
                Log::warning("DISCREPANCIA DE FICHAJE: Rostro (Empleado ID: {$foundEmployee->id}) no coincide con usuario autenticado (Empleado ID: {$authEmployee->id}).");
                return ['success' => false, 'message' => 'El rostro detectado no coincide con tu usuario. Fichaje denegado.', 'status' => 403];
            }

            return $this->processAttendanceForEmployee($foundEmployee, $attendanceMode);
        } catch (Throwable $e) {
            Log::error("Error procesando fichaje: " . $e->getMessage());
            return ['success' => false, 'message' => 'Ocurrió un error inesperado al procesar el fichaje.', 'status' => 500];
        }
    }

    private function processAttendanceForEmployee(Employee $employee, string $mode): array
    {
        $lastAttendance = $employee->attendances()->whereDate('created_at', today())->latest()->first();

        if ($mode === 'work') {
            $type = 'entry';
            if ($lastAttendance) {
                if ($lastAttendance->type === 'exit') {
                    return ['success' => false, 'message' => "{$employee->first_name}, tu jornada ya ha finalizado por hoy.", 'status' => 400];
                }
                if (in_array($lastAttendance->type, ['entry', 'break_end'])) {
                    $type = 'exit';
                }
            }
        } else { // mode === 'break'
            if (!$lastAttendance || in_array($lastAttendance->type, ['exit'])) {
                return ['success' => false, 'message' => 'Debes registrar tu entrada antes de tomar un descanso.', 'status' => 400];
            }
            $type = ($lastAttendance->type === 'break_start') ? 'break_end' : 'break_start';
        }

        // CAMBIO: Preparamos los datos de la asistencia en un array.
        $attendanceData = [
            'employee_id' => $employee->id,
            'type' => $type,
        ];
        
        // CAMBIO CLAVE: Si el tipo es 'entry', calculamos los minutos de retardo.
        if ($type === 'entry') {
            // Usamos el método público del servicio que inyectamos.
            // La hora de entrada es la hora actual.
            $lateMinutes = $this->incidentService->calculateLateMinutes($employee, now());
            
            // Si el resultado no es nulo y es mayor que 0, lo añadimos a los datos.
            if ($lateMinutes > 0) {
                $attendanceData['late_minutes'] = $lateMinutes;
                $attendanceData['late_ignored'] = false; // Por defecto, un nuevo retardo no está ignorado.
            }
        }
        
        // Creamos el registro de asistencia con todos los datos preparados.
        Attendance::create($attendanceData);

        $translatedType = self::translateAttendanceType($type);
        return [
            'success'   => true,
            'message'   => "¡Hola, {$employee->first_name}! Se registró tu {$translatedType}.",
            'employee'  => $employee,
            'type'      => $translatedType
        ];
    }

    /**
     * Finds an employee by their face using the Rekognition service.
     */
    private function findEmployeeByFace(string $imageData): ?Employee
    {
        $faceId = $this->rekognitionService->searchFaceByImage($imageData);
        if (!$faceId) {
            Log::info('Búsqueda facial: No se encontró coincidencia en Rekognition.');
            return null;
        }

        $employee = Employee::where('aws_rekognition_face_id', $faceId)->first();
        if (!$employee) {
            Log::warning("Rostro encontrado en Rekognition (FaceID: {$faceId}) pero no está asignado a ningún empleado.");
        }
        return $employee;
    }

    /**
     * Translates the attendance type enum to a user-friendly string.
     */
    public static function translateAttendanceType(string $type): string
    {
        return match ($type) {
            'entry'       => 'Entrada',
            'break_start' => 'Inicio de descanso',
            'break_end'   => 'Fin de descanso',
            'exit'        => 'Salida',
            default       => 'Fichaje',
        };
    }
}