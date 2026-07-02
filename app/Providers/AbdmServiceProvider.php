<?php

namespace App\Providers;

use App\Abdm\AbdmManager;
use App\Abdm\Contracts\AbdmGatewayClient;
use App\Abdm\Clients\NullGatewayClient;
use App\Abdm\Fhir\FhirMappingEngine;
use App\Abdm\Fhir\Mappers\PatientMapper;
use App\Abdm\Fhir\Mappers\PractitionerMapper;
use App\Abdm\Fhir\Mappers\OrganizationMapper;
use App\Abdm\Fhir\Mappers\EncounterMapper;
use App\Observers\AbdmConsultationObserver;
use App\Observers\AbdmPrescriptionObserver;
use App\Models\Consultation;
use App\Models\Prescription\Prescription;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the ABDM layer into the container.
 *
 * - Binds the AbdmGatewayClient interface to a concrete client chosen by config
 *   (default: NullGatewayClient — does nothing).
 * - Registers AbdmManager as a singleton, aliased 'abdm', so code can call
 *   app('abdm') or inject AbdmManager. This single binding is the seam that lets
 *   future phases swap in the real gateway with no changes to any caller.
 */
class AbdmServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Make config/abdm.php available even before publishing.
        $this->mergeConfigFrom(__DIR__ . '/../../config/abdm.php', 'abdm');

        // Resolve the gateway client class from config (defaults to the null client).
        $this->app->bind(AbdmGatewayClient::class, function ($app) {
            $driver  = config('abdm.driver', 'null');
            $clients = config('abdm.clients', []);
            $class   = $clients[$driver] ?? NullGatewayClient::class;

            return $app->make($class);
        });

        // The single front door. Aliased so app('abdm') works everywhere.
        $this->app->singleton(AbdmManager::class, function ($app) {
            return new AbdmManager($app->make(AbdmGatewayClient::class));
        });
        $this->app->alias(AbdmManager::class, 'abdm');

        // FHIR mapping engine with its registered mappers. New mappers (Practitioner,
        // Encounter, MedicationRequest...) are added to this list as they're built.
        $this->app->singleton(FhirMappingEngine::class, function ($app) {
            return new FhirMappingEngine([
                $app->make(PatientMapper::class),
                $app->make(PractitionerMapper::class),
                $app->make(OrganizationMapper::class),
                $app->make(EncounterMapper::class),
            ]);
        });
    }

    public function boot(): void
    {
        // Auto-generate FHIR documents when clinical records are finalized. These
        // observers are guarded by AbdmRecorder, which is a no-op while ABDM is
        // disabled — so registering them now is safe and changes nothing today.
        Consultation::observe(AbdmConsultationObserver::class);
        Prescription::observe(AbdmPrescriptionObserver::class);
    }
}
