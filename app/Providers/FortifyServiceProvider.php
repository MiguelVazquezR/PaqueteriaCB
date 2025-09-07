<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // LÓGICA DE AUTENTICACIÓN PERSONALIZADA
        Fortify::authenticateUsing(function (Request $request) {

            // Determinar el tipo de credencial: email o número de empleado
            $identifierField = 'email'; // Por defecto, el campo es email
            $identifierValue = $request->input('email'); // El input del formulario lo seguiremos llamando 'email' por simplicidad

            // Si el valor ingresado NO contiene '@', asumimos que es un número de empleado
            if (!filter_var($identifierValue, FILTER_VALIDATE_EMAIL)) {
                // Buscamos al empleado por su número
                $employee = Employee::where('employee_number', $identifierValue)->first();

                // Si no encontramos al empleado o no tiene un usuario asociado, la autenticación falla
                if (!$employee || !$employee->user) {
                    return null;
                }

                // Si lo encontramos, el usuario a verificar es el que está asociado a ese empleado
                $user = $employee->user;
            } else {
                // Si es un email, buscamos al usuario directamente en la tabla de usuarios
                $user = User::where('email', $identifierValue)->first();
            }

            // 4. Verificar la contraseña
            if ($user && Hash::check($request->input('password'), $user->password)) {
                return $user; // Si el usuario existe y la contraseña es correcta, lo retornamos
            }

            return null; // En cualquier otro caso, la autenticación falla
        });

        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())) . '|' . $request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
