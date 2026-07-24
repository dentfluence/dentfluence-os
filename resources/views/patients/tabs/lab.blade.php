
{{-- Lab Cases Tab --}}
@php $labCases = $patient->labCases()->with(['doctor', 'vendor', 'items'])->latest()->get(); @endphp
@php $labDoctors = \App\Models\User::orderBy('name')->get(); @endphp
@php $labVendors = \App\Models\LabVendor::active()->with(['services' => fn($q) => $q->active()])->orderBy('name')->get(['id', 'name']); @endphp
@include('patients.partials.lab-tab', ['cases' => $labCases, 'doctors' => $labDoctors, 'vendors' => $labVendors])
