# 02 · Target Architecture
### The ABDM-native layered design for Dentfluence OS

**Status:** DESIGN ONLY. Mermaid diagrams render in VS Code / GitHub / any Mermaid viewer.
**Premise from doc 00:** ABDM is a *core layer*. Modules never call ABDM APIs directly — they call the **ABDM Layer**, which owns identity, consent, FHIR, exchange, sync and security.

---

## 1. The big picture — layered architecture

```mermaid
flowchart TB
    subgraph PRES["① Presentation"]
        WEB["Blade Web UI"]
        FLUT["Flutter Mobile"]
        AI["AI Secretary (Tulip)"]
    end

    subgraph API["② API Layer (/api/v1)"]
        INT["Internal APIs<br/>(existing controllers)"]
        FAPI["FHIR APIs<br/>(/fhir/* — future)"]
        EXTAPI["External/ABDM APIs<br/>(callback endpoints)"]
    end

    subgraph APP["③ Application / Service Layer"]
        SVC["Domain Services<br/>(PatientService, ConsultationService,<br/>PrescriptionService, BillingService…)"]
    end

    subgraph DOM["④ Domain Layer"]
        MOD["Eloquent Models + Business Rules<br/>(Patient, Consultation, Prescription…)"]
        REPO["Repositories<br/>(query abstraction)"]
    end

    subgraph ABDM["⑤ ABDM LAYER  (the new core)"]
        direction TB
        ABHA["ABHA Manager"]
        HPR["HPR Manager"]
        HFR["HFR Manager"]
        CONSENT["Consent Manager"]
        FHIRE["FHIR Mapping Engine"]
        HIE["Health Information Exchange"]
        AUTHM["ABDM Auth Manager"]
        SYNC["Sync Service"]
        QUEUE["Queue Service"]
        AUDS["Audit Service"]
        SEC["Security Layer"]
    end

    subgraph INFRA["⑥ Infrastructure"]
        DB[("MySQL")]
        REDIS[("Redis / Queue")]
        STORE["File / Object Store"]
        KMS["Secret / Key Store"]
        OLLAMA["Local AI (Ollama)"]
    end

    subgraph EXT["⑦ External (future)"]
        GW["ABDM Gateway"]
        HIPHIU["HIP / HIU Bridge"]
        REG["ABHA / HPR / HFR Registries"]
    end

    PRES --> API --> APP --> DOM
    APP --> ABDM
    DOM --> REPO --> DB
    ABDM --> INFRA
    AI --> CONSENT
    ABDM -. "only this layer talks out" .-> EXT
    SYNC --> QUEUE --> REDIS
    SEC --> KMS
```

**The single most important arrow:** only the ABDM Layer (⑤) crosses into External (⑦). No controller, service, or model ever imports an ABDM SDK. This is the rule that makes future API changes a one-layer edit.

---

## 2. Folder structure (proposed, additive)

The existing `app/` is untouched; we add an `app/Abdm/` namespace and small seams.

```
app/
├── Http/Controllers/            # EXISTING — unchanged
│   └── Api/V1/                  # EXISTING internal APIs
│       └── Fhir/                # NEW (future) FHIR endpoints
├── Models/                      # EXISTING + new identity/consent models
│   ├── Identity/                # NEW  PatientIdentifier, PractitionerIdentifier…
│   ├── Consent/                 # NEW  Consent, ConsentArtefact, ConsentAudit
│   └── Fhir/                    # NEW  FhirDocument, TerminologyMap
├── Services/                    # EXISTING domain services — unchanged
│
└── Abdm/                        # ★ NEW — the ABDM Layer (single entry point)
    ├── AbdmManager.php          # facade other code calls
    ├── Identity/
    │   ├── AbhaManager.php
    │   ├── HprManager.php
    │   └── HfrManager.php
    ├── Consent/
    │   ├── ConsentManager.php
    │   └── ConsentPolicy.php
    ├── Fhir/
    │   ├── FhirMappingEngine.php
    │   ├── Mappers/             # PatientMapper, EncounterMapper, …
    │   ├── Bundles/             # OPConsultationBundle, PrescriptionBundle…
    │   └── Terminology/         # ConceptMap resolver
    ├── Exchange/
    │   ├── HealthInformationExchange.php   # HIP + HIU behaviour
    │   └── CareContextLinker.php
    ├── Auth/
    │   └── AbdmAuthManager.php   # OAuth2 gateway tokens (isolated)
    ├── Sync/
    │   ├── SyncService.php
    │   ├── Outbox.php / Inbox.php / RetryQueue.php
    │   └── ConflictResolver.php
    ├── Security/
    │   ├── EncryptionService.php
    │   ├── SignatureService.php
    │   └── TokenRotator.php
    ├── Audit/
    │   └── AbdmAuditService.php  # hash-chained
    ├── Contracts/               # interfaces (so impl can swap: sandbox/prod/mock)
    │   ├── AbdmGatewayClient.php
    │   └── RegistryClient.php
    └── Clients/
        ├── NullGatewayClient.php # DEFAULT this phase — does nothing
        ├── SandboxGatewayClient.php   # future
        └── ProductionGatewayClient.php# future
```

**Why `Contracts/` + `Clients/`:** the ABDM Layer codes against *interfaces*. Today the bound implementation is `NullGatewayClient` (no-op, feature-flag off). Later we bind Sandbox, then Production — **zero changes** to any module or service. This is the migration's core future-proofing.

---

## 3. The golden path — how a module emits a record (data-flow)

```mermaid
flowchart LR
    A["Doctor completes<br/>Consultation"] --> B["ConsultationService<br/>saves internal model"]
    B --> C{"abdm_enabled<br/>flag?"}
    C -- "off (today)" --> Z["Done — internal only"]
    C -- "on (future)" --> D["AbdmManager.recordEncounter()"]
    D --> E["FHIR Mapping Engine<br/>Internal → FHIR Encounter+Bundle"]
    E --> F["Consent Manager<br/>valid consent for share?"]
    F -- "no" --> G["Store FHIR doc locally<br/>queue care-context only"]
    F -- "yes" --> H["Sync Service → Outbox"]
    H --> I["Security: sign + encrypt"]
    I --> J["Queue Service → ABDM Gateway<br/>(via bound client)"]
    J --> K["Audit Service logs<br/>(hash-chained + Provenance)"]
```

The module (`ConsultationService`) does **one** new thing: call `AbdmManager.recordEncounter($consultation)`. Everything else is internal to the layer and flag-gated. Today the flag is off → the call is skipped entirely.

---

## 4. Sequence — ABHA verification & linking (future, but designed now)

```mermaid
sequenceDiagram
    participant FD as Front Desk
    participant UI as Web/Mobile
    participant PS as PatientService
    participant AM as AbhaManager
    participant GW as ABDM Gateway (future)
    participant DB as patient_identifiers

    FD->>UI: Enter ABHA number / address
    UI->>PS: linkAbha(patientId, abha)
    PS->>AM: verify(abha)
    AM->>GW: OTP init (bound client)
    GW-->>AM: txnId
    AM-->>UI: prompt OTP
    FD->>UI: enter OTP
    UI->>AM: confirm(txnId, otp)
    AM->>GW: verify OTP
    GW-->>AM: ABHA profile + status
    AM->>DB: upsert identifier(type=ABHA, verified)
    AM->>PS: status = verified
    PS-->>UI: show ABHA card (verified)
```

This phase we build the `AbhaManager` interface + `patient_identifiers` table + UI card; the `GW` calls resolve to `NullGatewayClient` until Sandbox arrives.

---

## 5. Sequence — Consent-gated AI access (the safety-critical path)

```mermaid
sequenceDiagram
    participant U as User
    participant T as Tulip (AI)
    participant TR as ToolRegistry
    participant CM as ConsentManager
    participant HIE as Health Info Exchange

    U->>T: "Show this patient's external history"
    T->>TR: call ExternalHistoryTool(patientId)
    TR->>CM: assertValid(patientId, purpose=CARE_MGMT)
    alt no valid consent
        CM-->>TR: DENY
        TR-->>T: blocked (no consent)
        T-->>U: "I can't access external records without consent. Request one?"
    else valid consent
        CM-->>TR: OK (consentId)
        TR->>HIE: fetch(patientId, consentId)
        HIE-->>TR: FHIR bundle
        TR->>TR: log ai_action_logs(consentId)
        TR-->>T: data
        T-->>U: summary
    end
```

The AI literally cannot reach external data except through a tool that calls `ConsentManager::assertValid()` first. Default: `ExternalHistoryTool` is disabled by flag.

---

## 6. Layer responsibilities (contract table)

| Layer | Owns | Must NOT do |
|---|---|---|
| Presentation | UI, capture, display | business logic, FHIR |
| API | routing, auth, validation, envelope | clinical rules |
| Service | orchestration, transactions, domain workflow | hand-write FHIR, call ABDM |
| Domain | entities, invariants, relationships | know ABDM exists |
| **ABDM Layer** | identity, consent, FHIR, exchange, sync, security, ABDM audit | leak clinical logic back up |
| Infrastructure | persistence, queue, store, keys | business decisions |

**Dependency rule:** dependencies point *downward and inward*. Domain never depends on ABDM Layer; **Service** depends on ABDM Layer (the seam). This keeps the domain pure and testable.

---

## 7. API architecture — three clear surfaces

```mermaid
flowchart TB
    subgraph Internal["Internal APIs  /api/v1/*  (EXISTING)"]
        P["/patients /consultations /prescriptions …"]
    end
    subgraph FHIR["FHIR APIs  /fhir/r4/*  (NEW, future)"]
        FP["/fhir/r4/Patient /Encounter /MedicationRequest"]
    end
    subgraph ABDMcb["ABDM Callback APIs  /abdm/*  (NEW, future)"]
        CB["/abdm/consent/notify<br/>/abdm/hip/care-contexts<br/>/abdm/hiu/on-fetch"]
    end
    Internal --> SVC2["Services"]
    FHIR --> FE["FHIR Engine"]
    ABDMcb --> HIE2["Health Info Exchange"]
```

- **Internal APIs** — unchanged; your app and Flutter keep working exactly as today.
- **FHIR APIs** — read-only FHIR projection of internal data (future; for PHR/HIU).
- **ABDM Callback APIs** — endpoints the ABDM Gateway calls back into (consent notifications, data requests). Secured separately (doc 07). Built later; the *routes are reserved* now.

---

## 8. Repository layer (light introduction)

Today services query Eloquent directly — fine. For ABDM we introduce **thin repositories only where the ABDM Layer needs to read across modules** (e.g. assembling a full patient health record bundle), so the layer doesn't reach into 10 models. Existing services are not forced to adopt repositories — this is additive and optional.

---

## 9. Deployment architecture

```mermaid
flowchart TB
    subgraph Clinic["Clinic (Laragon / on-prem or VPS)"]
        APP2["Laravel App (PHP-FPM)"]
        WORKER["Queue Workers<br/>(sync, FHIR gen, signing)"]
        MYSQL[("MySQL")]
        REDIS2[("Redis")]
        OLL["Ollama (local AI/GPU)"]
        VAULT["Secret Store<br/>(env-encrypted / KMS)"]
    end
    subgraph Cloud["ABDM (external, future)"]
        GW2["ABDM Gateway"]
    end
    APP2 --> MYSQL
    APP2 --> REDIS2
    WORKER --> REDIS2
    APP2 --> OLL
    APP2 --> VAULT
    WORKER -. "TLS, mutual auth" .-> GW2
```

**Key deployment decisions:**
- **Queue workers are mandatory** for ABDM (sync/signing must be async). Today the app runs largely synchronously; Phase 1 introduces a worker (`php artisan queue:work`) — additive.
- **Secrets never in DB or code** — only references in DB; actual keys in an encrypted secret store / KMS (doc 07).
- **AI stays local** (Ollama) — patient data never leaves for inference, which is itself a consent/privacy win.
- ABDM egress is **only from workers**, over TLS with mutual auth — a single, auditable choke point.

---

## 10. Scalability strategy (15-year horizon)

| Concern | Strategy |
|---|---|
| Multi-clinic / multi-facility | `branch_id` already pervasive; promote to first-class tenant scope; per-branch HFR/HIP config (`facility_abdm_config`). |
| Document volume | FHIR docs stored as references + object store, not BLOBs in MySQL; hash-chained audit in append-only tables. |
| Sync throughput | Queue-backed outbox/inbox with backpressure; retprovider rate-limit aware; batchable. |
| Terminology growth | Data-driven `terminology_maps` (ConceptMap) — add codes without deploys. |
| Registry/API churn | `Contracts/`+`Clients/` — swap implementations, not callers. |
| Read scale | Repositories + read models for heavy bundle assembly; cache verified identifiers. |
| Offline clinics | Sync Engine offline mode + conflict resolver (doc 06); app fully usable without ABDM. |
| Schema evolution | Additive-only + identifier normalization removes the "add a column per identity" churn forever. |

---

## 11. What is genuinely new vs. reused

**Reused (Dentfluence already has, big head-start):**
- Audit foundation (`audit_logs`, `Auditable`, `ai_action_logs`) → extend for ABDM.
- Tool-gated AI with confirm-cards → perfect consent-gate host.
- ICD-10 on consultations, coded `rx_drugs`, CDSS → FHIR-ready clinical data.
- Append-only ledgers (stock/wallet) → the pattern for sync outbox + consent audit.
- Sanctum API + envelope + RBAC → host for FHIR/ABDM endpoints.
- Versioned prescriptions, soft deletes → provenance-friendly.

**Genuinely new (this migration builds):**
- ABDM Layer (`app/Abdm/`) + `Contracts/Clients` binding.
- Identifier normalization (`patient_identifiers`, etc.).
- FHIR Mapping Engine + `fhir_documents` + `terminology_maps`.
- Consent Engine (`consents`/`consent_artefacts`/`consent_audit`).
- Sync Engine (`sync_*` queues) + queue workers.
- Security Layer (signing, encryption-at-rest for identifiers, ABDM token rotation).
- Per-facility ABDM settings + feature flags.

> Next: `03-DATA-MODEL-AND-SCHEMA.md` specifies every new table and column precisely; `04`–`07` detail the engines; `08` sequences the build.
