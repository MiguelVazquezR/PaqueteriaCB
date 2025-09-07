<?php

namespace App\Services;

use App\Models\User;
use App\Models\Employee;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

class UserService
{
    public function __construct(private RekognitionService $rekognitionService)
    {
    }

    public function createEmployeeAndUser(array $validatedData): User|Employee
    {
        return DB::transaction(function () use ($validatedData) {
            $employeeData = $this->getEmployeeDataFromRequest($validatedData);
            $employee = Employee::create($employeeData);

            $employee->schedules()->attach($validatedData['schedule_id'], ['start_date' => now()]);

            if (isset($validatedData['facial_image'])) {
                $this->handleFacialImageUpload($validatedData['facial_image'], $employee);
            }

            if ($validatedData['create_user_account']) {
                $user = User::create([
                    'name' => $validatedData['first_name'] . ' ' . $validatedData['last_name'],
                    'email' => $validatedData['email'],
                    'password' => Hash::make($validatedData['password']),
                ]);

                if (isset($validatedData['facial_image'])) {
                    $user->updateProfilePhoto($validatedData['facial_image']);
                }

                $role = Role::findById($validatedData['role_id']);
                $user->assignRole($role);

                $employee->user()->associate($user)->save();
                return $user;
            }

            return $employee;
        });
    }
    
    public function updateEmployeeAndUser(User $user, array $validatedData): User
    {
        $employee = $user->employee;

        DB::transaction(function () use ($user, $employee, $validatedData) {
            $user->update([
                'name' => $validatedData['first_name'] . ' ' . $validatedData['last_name'],
                'email' => $validatedData['email'],
            ]);

            if (isset($validatedData['password'])) {
                $user->password = Hash::make($validatedData['password']);
                $user->save();
            }

            if ($employee) {
                if (isset($validatedData['facial_image'])) {
                    $this->handleFacialImageUpload($validatedData['facial_image'], $employee, true);
                    $user->updateProfilePhoto($validatedData['facial_image']);
                } elseif ($validatedData['delete_photo'] ?? false) {
                    $this->handleFacialImageDeletion($employee);
                    $user->deleteProfilePhoto();
                }

                $employeeData = $this->getEmployeeDataFromRequest($validatedData);
                if ($validatedData['is_active']) {
                    $employeeData['termination_date'] = null;
                    $employeeData['termination_reason'] = null;
                }
                $employee->update($employeeData);
                $employee->schedules()->sync([$validatedData['schedule_id'] => ['start_date' => now()]]);
            }

            $role = Role::findById($validatedData['role_id']);
            $user->syncRoles($role);
        });

        return $user;
    }

    private function handleFacialImageUpload(UploadedFile $image, Employee $employee, bool $isUpdate = false): void
    {
        if ($isUpdate && $employee->aws_rekognition_face_id) {
            $this->rekognitionService->deleteFace($employee->aws_rekognition_face_id);
        }

        try {
            $faceId = $this->rekognitionService->indexFace($image->get(), $employee->employee_number);
            if ($faceId) {
                $employee->aws_rekognition_face_id = $faceId;
                $employee->save();
            } else {
                session()->flash('warning', 'El rostro no pudo ser procesado por Rekognition. Asegúrate de que la imagen sea clara.');
            }
        } catch (\Exception $e) {
            Log::error("Error al indexar rostro para empleado {$employee->employee_number}: " . $e->getMessage());
            session()->flash('warning', 'Hubo un error de comunicación al registrar el rostro.');
        }
    }

    private function handleFacialImageDeletion(Employee $employee): void
    {
        if ($employee->aws_rekognition_face_id) {
            $this->rekognitionService->deleteFace($employee->aws_rekognition_face_id);
            $employee->aws_rekognition_face_id = null;
            $employee->save();
        }
    }

    private function getEmployeeDataFromRequest(array $data): array
    {
        $employeeFields = (new Employee())->getFillable();
        return collect($data)->only($employeeFields)->all();
    }
}