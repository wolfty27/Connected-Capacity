<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\RetirementHomeController;
use App\Http\Controllers\PatientsController;
use App\Http\Controllers\HospitalsController;
use App\Http\Controllers\BookingsController;
use App\Http\Controllers\MyProfileController;
use App\Http\Controllers\CalendlyController;
use Illuminate\Support\Facades\View;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

//Route::get('/', function () {
//    return view('welcome');
//});

Route::get('/', [HomeController::class, 'index']);
Route::get('/login', [UserController::class, 'loginView']);
Route::post('/login', [UserController::class, 'login']);
Route::get('/logout', [UserController::class, 'logout']);

Route::group(['middleware' => ['authenticated.routes']], function (){
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/hospitals', [HospitalsController::class, 'index']);
    Route::get('/hospitals/view/{id}', [HospitalsController::class, 'view']);
    Route::get('/patients', [PatientsController::class, 'index']);
    Route::get('/placed-patients', [PatientsController::class, 'placedPatients']);
    Route::get('/patient/{id}/assessment-form', [PatientsController::class, 'assessmentForm']);
    Route::post('/patient/{id}/assessment-form/store', [PatientsController::class, 'storeAssessmentForm']);
    Route::get('/retirement-homes', [RetirementHomeController::class, 'index']);
    Route::get('/retirement-homes/fetch-status/{id}', [RetirementHomeController::class, 'getRetirementHomeStatus']);
    Route::get('/retirement-homes/change-status/{id}/{id2}', [RetirementHomeController::class, 'changeRetirementHomeStatus']);
    Route::get('/retirement-homes/view/{id}', [RetirementHomeController::class, 'view']);
    Route::get('/retirement-homes/{id}/patients', [RetirementHomeController::class, 'myPatients']);
    Route::get('/retirement-homes/files/{id}', [RetirementHomeController::class, 'files']);
    Route::get('/retirement-homes/gallery/{id}', [RetirementHomeController::class, 'gallery']);
    Route::get('/retirement-homes/gallery/justview/{id}', [RetirementHomeController::class, 'galleryJustView']);
    Route::get('/patients/create', [PatientsController::class, 'create']);
    Route::post('/patients/store', [PatientsController::class, 'store']);
    Route::get('/patients/view/{id}', [PatientsController::class, 'view']);
    Route::get('/patients/view/appointed/{id}/{id2}', [PatientsController::class, 'appointedView']);
    Route::get('/patients/delete/{id}', [PatientsController::class, 'delete']);
    Route::get('/patients/edit/{id}', [PatientsController::class, 'edit']);
    Route::post('/patients/update/{id}', [PatientsController::class, 'update']);
    Route::get('/bookings', [BookingsController::class, 'index']);
    Route::get('/bookings/hospital', [BookingsController::class, 'bookingHospital']);
    Route::post('/appointments', [BookingsController::class, 'offer']);
    // Route::get('/book-appointment/{id}', [BookingsController::class, 'bookAppointment']);
    Route::post('/book-calendly-appointment', [CalendlyController::class, 'bookCalendlyAppointment']);
    Route::get('/in-person-assessment/{id}', [BookingsController::class, 'inPersonAssessment']);
    Route::post('/in-person-assessment/store', [BookingsController::class, 'storeInPersonAssessment']);
    Route::post('/in-person-assessment/reject', [BookingsController::class, 'rejectInPersonAssessment']);
    Route::get('/booking/{id}/{status}', [BookingsController::class, 'updateStatus']);
    Route::get('/view-booking/{id}', [BookingsController::class, 'bookingView']);
    Route::get('/my-account', [MyProfileController::class, 'profile']);
    Route::post('/my-account/update/{id}', [MyProfileController::class, 'updateProfile']);
    Route::post('/my-account/update-password/{id}', [MyProfileController::class, 'updatePassword']);
    Route::get('/my-account/change-password', [MyProfileController::class, 'changePassword']);
    Route::delete('/my-account/delete-gallery/{id}', [MyProfileController::class, 'deleteGallery']);
    Route::get('/my-account/fetch-gallery', [MyProfileController::class, 'getGallery']);
    Route::get('/my-account/fetch-gallery-for-admin/{id}', [MyProfileController::class, 'getGalleryForAdmin']);
    Route::post('/my-account/upload-gallery/{id}', [MyProfileController::class, 'uploadGallery']);
    Route::get('/outh/calendly', [CalendlyController::class, 'index']);
    Route::get('/my-calendly', [CalendlyController::class, 'getScheduledEvents']);
    Route::get('/my-calendly/get-invitee-data', [CalendlyController::class, 'getInviteeData']);
    Route::get('/logout/calendly', [CalendlyController::class, 'logoutCalendly']);


    Route::get('/demo', function(){
        return View::make('demo');
    });

    Route::group(['middleware' => ['admin.routes']], function () {
        Route::get('/retirement-homes/create', [RetirementHomeController::class, 'create']);
        Route::post('/retirement-homes/store', [RetirementHomeController::class, 'store']);
        Route::get('/retirement-homes/edit/{id}', [RetirementHomeController::class, 'edit']);
        Route::post('/retirement-homes/update/{id}', [RetirementHomeController::class, 'update']);
        Route::get('/retirement-homes/delete/{id}', [RetirementHomeController::class, 'delete']);
        Route::get('/hospitals/create', [HospitalsController::class, 'create']);
        Route::get('/hospitals/edit/{id}', [HospitalsController::class, 'edit']);
        Route::post('/hospitals/update/{id}', [HospitalsController::class, 'update']);
        Route::get('/hospitals/delete/{id}', [HospitalsController::class, 'delete']);
        Route::post('/hospitals/store', [HospitalsController::class, 'store']);
    });
});

