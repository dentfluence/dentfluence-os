@include('patients.partials.membership-tab', [
    'activeMembership'  => $activeMembership  ?? null,
    'membershipHistory' => $membershipHistory  ?? collect(),
    'benefitLogs'       => $benefitLogs        ?? collect(),
])
