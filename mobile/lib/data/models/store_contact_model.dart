/// The shop's public contact details.
///
/// Mirrors GET /api/v1/storefront/contact. The numbers and links are set in the
/// dashboard, so they are read from the server rather than hard-coded in the app.
class StoreContact {
  final String whatsapp;
  final String phone;
  final String email;
  final String playStoreUrl;
  final String appStoreUrl;

  const StoreContact({
    this.whatsapp = '',
    this.phone = '',
    this.email = '',
    this.playStoreUrl = '',
    this.appStoreUrl = '',
  });

  factory StoreContact.fromJson(Map<String, dynamic> json) {
    return StoreContact(
      whatsapp: json['whatsapp']?.toString() ?? '',
      phone: json['phone']?.toString() ?? '',
      email: json['email']?.toString() ?? '',
      playStoreUrl: json['play_store_url']?.toString() ?? '',
      appStoreUrl: json['app_store_url']?.toString() ?? '',
    );
  }

  /// The WhatsApp number with everything except digits stripped.
  ///
  /// The dashboard may store the number with spaces, dashes or a leading plus,
  /// but `wa.me` only accepts digits, so this is what the link is built from.
  String get whatsappDigits => whatsapp.replaceAll(RegExp(r'[^0-9]'), '');

  bool get hasWhatsapp => whatsappDigits.isNotEmpty;
  bool get hasPhone => phone.trim().isNotEmpty;
  bool get hasEmail => email.trim().isNotEmpty;
}
