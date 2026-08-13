# Mobile WhatsApp Click-to-Chat — Flutter Integration

**Status:** backend done (2026-07-18); Flutter code below is paste-ready but must be
added in the mobile repo (not in this repo).

## What the backend gives you

```
POST /api/v1/patients/{patient}/whatsapp/link
Auth: Bearer <sanctum token>   (same as every other /api/v1 call)

Body (JSON):
{
  "context": "appointment_reminder",   // or appointment_confirmation | review_request | recall | birthday | generic
  "message": null,                       // used only for context "generic" (free text)
  "params":  {                           // display values the template needs
      "date":   "20 Jul",
      "time":   "4:00 PM",
      "doctor": "Asha Mehta"             // bare name; server turns it into " with Dr. Asha Mehta"
  },
  "type": "service"                      // "service" (default) | "marketing"
}

Success (200), standard API envelope:
{ "success": true, "data": { "url": "https://wa.me/9198...?text=...", "phone": "9198..." } }

Blocked by consent / do-not-contact (422):
{ "success": false, "message": "Patient has not granted 'WhatsApp' consent (DPDP)." }
```

The URL, phone normalization (+91), consent gate and message copy are all resolved
server-side by `WhatsAppLinkService` — the same engine the web app uses, so mobile and
web never drift. The app's only job is: POST, then open `data.url` with `url_launcher`.

## Flutter

`pubspec.yaml` (if not already present):

```yaml
dependencies:
  url_launcher: ^6.3.0
```

### Service method

Add to your API service (adapt `_dio` / base URL / token to your existing client):

```dart
import 'package:url_launcher/url_launcher.dart';

/// Opens WhatsApp (click-to-chat) for a patient. Returns null on success,
/// or an error message to show the user (e.g. consent not granted).
Future<String?> sendPatientWhatsApp({
  required int patientId,
  required String context,          // appointment_reminder | review_request | recall | birthday | generic
  Map<String, dynamic>? params,
  String? message,                  // only for context "generic"
  String type = 'service',
}) async {
  try {
    final res = await _dio.post(
      '/api/v1/patients/$patientId/whatsapp/link',
      data: {
        'context': context,
        if (message != null) 'message': message,
        if (params != null) 'params': params,
        'type': type,
      },
    );

    final data = res.data;
    if (data['success'] == true && data['data']?['url'] != null) {
      final uri = Uri.parse(data['data']['url'] as String);
      if (await canLaunchUrl(uri)) {
        await launchUrl(uri, mode: LaunchMode.externalApplication);
        return null;
      }
      return 'WhatsApp is not installed on this device.';
    }
    return (data['message'] as String?) ?? 'Could not open WhatsApp for this patient.';
  } on DioException catch (e) {
    // 422 = consent / do-not-contact block — surface the server message.
    final msg = e.response?.data?['message'];
    return (msg is String) ? msg : 'Could not reach WhatsApp send. Please try again.';
  }
}
```

### Example call sites

Appointment reminder (from an appointment card):

```dart
final err = await api.sendPatientWhatsApp(
  patientId: apt.patientId,
  context: 'appointment_reminder',
  params: {
    'date':   apt.dateLabel,     // "20 Jul"
    'time':   apt.timeLabel,     // "4:00 PM"
    'doctor': apt.doctorName,    // "Asha Mehta"
  },
);
if (err != null && context.mounted) {
  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(err)));
}
```

Generic message from the patient profile (staff types their own text in WhatsApp):

```dart
await api.sendPatientWhatsApp(patientId: patient.id, context: 'generic');
```

Review request:

```dart
await api.sendPatientWhatsApp(patientId: patient.id, context: 'review_request');
```

### UI

Add a WhatsApp icon button (`Icons.chat` / a brand-whatsapp asset, green `#25D366`)
wherever the web app has one: appointment cards, patient profile header, recall rows.
On tap call `sendPatientWhatsApp(...)`; show the returned error string in a SnackBar if
non-null. No local phone formatting or message building in the app — the server owns it.
```
