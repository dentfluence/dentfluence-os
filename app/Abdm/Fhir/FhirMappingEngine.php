<?php

namespace App\Abdm\Fhir;

use App\Abdm\Fhir\Contracts\Mapper;
use App\Abdm\Fhir\Validation\FhirValidator;
use App\Models\FhirDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * FhirMappingEngine — the ONLY thing that turns internal models into FHIR.
 *
 * Modules never build FHIR by hand; they ask the engine. The engine finds the right
 * mapper (registered by model class), produces the FHIR resource, and can persist it
 * to fhir_documents with a stable id, version and content hash. One place to map,
 * one place to certify, one place to evolve.
 */
class FhirMappingEngine
{
    /** @var array<class-string, Mapper>  model class => mapper */
    private array $mappers = [];

    private FhirValidator $validator;

    /** @param iterable<Mapper> $mappers */
    public function __construct(iterable $mappers = [], ?FhirValidator $validator = null)
    {
        foreach ($mappers as $mapper) {
            $this->register($mapper);
        }
        $this->validator = $validator ?? new FhirValidator();
    }

    public function validator(): FhirValidator
    {
        return $this->validator;
    }

    public function register(Mapper $mapper): void
    {
        $this->mappers[$mapper->supports()] = $mapper;
    }

    public function hasMapperFor(Model $model): bool
    {
        return isset($this->mappers[$model::class]);
    }

    /** Map a model to a FHIR resource array (no persistence). */
    public function map(Model $model): array
    {
        $mapper = $this->mappers[$model::class]
            ?? throw new RuntimeException("No FHIR mapper registered for " . $model::class);

        return $mapper->toFhir($model);
    }

    /**
     * Map AND persist as a fhir_documents row (versioned + hashed).
     * Returns the saved FhirDocument.
     */
    public function persist(Model $model, string $status = 'final', ?int $userId = null): FhirDocument
    {
        $mapper   = $this->mappers[$model::class]
            ?? throw new RuntimeException("No FHIR mapper registered for " . $model::class);
        $resource = $mapper->toFhir($model);

        return $this->storeDocument($model, $mapper->resourceType(), $resource, null, $status, $userId, true);
    }

    /**
     * Persist a full FHIR Bundle (from an assembler) as a fhir_documents row,
     * owned by the source record (e.g. a Consultation or Prescription).
     */
    public function persistBundle(Model $owner, array $bundle, string $bundleType, string $status = 'final', ?int $userId = null): FhirDocument
    {
        return $this->storeDocument($owner, 'Bundle', $bundle, $bundleType, $status, $userId, false);
    }

    /**
     * Shared store path: validate → version → hash → save. If the resource has
     * validation ERRORS it is never stored as 'final' — it is downgraded to 'draft'
     * and the issues are logged, so nothing invalid can flow onward to ABDM.
     */
    private function storeDocument(Model $owner, string $resourceType, array $resource, ?string $bundleType, string $status, ?int $userId, bool $writeBack): FhirDocument
    {
        $json   = json_encode($resource, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $hash   = hash('sha256', $json);
        $fhirId = $resource['id'] ?? (string) Str::uuid();

        // Validate. Errors force the document to 'draft' (never silently final).
        $result = $this->validator->validate($resource);
        if (! $result['ok']) {
            Log::warning('[ABDM:fhir] validation errors — storing as draft', [
                'owner'  => $owner::class . '#' . $owner->getKey(),
                'type'   => $resourceType,
                'errors' => $result['errors'],
            ]);
            $status = 'draft';
        }

        // Idempotent: if the latest version for this owner+type is identical, reuse it.
        $latest = FhirDocument::where('owner_type', $owner::class)
            ->where('owner_id', $owner->getKey())
            ->where('resource_type', $resourceType)
            ->orderByDesc('version')
            ->first();

        if ($latest && $latest->content_hash === $hash) {
            return $latest;
        }

        $doc = FhirDocument::create([
            'owner_type'    => $owner::class,
            'owner_id'      => $owner->getKey(),
            'resource_type' => $resourceType,
            'fhir_id'       => $fhirId,
            'version'       => $latest ? $latest->version + 1 : 1,
            'status'        => $status,
            'bundle_type'   => $bundleType,
            'content'       => $json,
            'content_hash'  => $hash,
            'generated_by'  => $userId,
        ]);

        if ($writeBack) {
            $this->writeBackFhirId($owner, $fhirId);
        }

        return $doc;
    }

    /** Persist the FHIR logical id back onto the source record if it has a matching column. */
    private function writeBackFhirId(Model $model, string $fhirId): void
    {
        $column = match (true) {
            $model instanceof \App\Models\Patient => 'fhir_resource_id',
            $model instanceof \App\Models\Branch  => 'fhir_organization_id',
            default                                => null,
        };

        if ($column && empty($model->{$column})) {
            $model->forceFill([$column => $fhirId])->saveQuietly();
        }
    }
}
