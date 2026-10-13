import 'package:url_launcher/url_launcher.dart';

/// Helpers for turning contact details into links the phone can open.
///
/// Kept in one place so every screen builds `wa.me`, `tel:` and `mailto:` links
/// the same way instead of repeating the escaping rules.

/// Digits only, because `wa.me` rejects spaces, dashes and a leading plus.
String whatsappDigits(String number) =>
    number.replaceAll(RegExp(r'[^0-9]'), '');

/// A `wa.me` link with an optional prefilled message.
///
/// Returns an empty string when the number has no digits, so callers can check
/// before trying to open it.
String whatsAppUrl(String number, {String? message}) {
  final digits = whatsappDigits(number);
  if (digits.isEmpty) return '';

  final base = 'https://wa.me/$digits';
  final text = message?.trim();
  if (text == null || text.isEmpty) return base;

  return '$base?text=${Uri.encodeComponent(text)}';
}

/// A `tel:` link for the phone dialler.
String phoneUrl(String number) {
  final cleaned = number.replaceAll(RegExp(r'[^\d+]'), '');
  if (cleaned.isEmpty) return '';
  return 'tel:$cleaned';
}

/// A `mailto:` link for the mail app.
String emailUrl(String email) {
  final address = email.trim();
  if (address.isEmpty) return '';
  return 'mailto:$address';
}

/// Opens a link outside the app, e.g. WhatsApp, the phone dialler or the mail
/// app. Does nothing when the link is empty or cannot be parsed.
Future<void> openUrl(String url) async {
  final trimmed = url.trim();
  if (trimmed.isEmpty) return;

  final uri = Uri.tryParse(trimmed);
  if (uri == null) return;

  try {
    await launchUrl(uri, mode: LaunchMode.externalApplication);
  } catch (_) {
    // Handing off to another app can fail (nothing installed). The caller
    // decides what, if anything, to tell the person.
  }
}
