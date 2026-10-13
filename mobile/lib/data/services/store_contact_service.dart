import '../../core/config/api_config.dart';
import '../../core/network/api_client.dart';
import '../../core/network/api_exception.dart';
import '../models/store_contact_model.dart';

/// Reads the shop's public contact details.
///
/// The business WhatsApp number, phone, email and store links are owned by the
/// server so changing them in the dashboard takes effect without a new app
/// release.
class StoreContactService {
  final ApiClient _apiClient;

  StoreContactService({ApiClient? apiClient})
      : _apiClient = apiClient ?? ApiClient();

  Future<StoreContact> fetchContact() async {
    final response = await _apiClient.get(ApiConfig.storeContact);

    if (response['success'] == false) {
      throw ApiException(
        message: response['message'] as String? ??
            'Could not load contact details',
        code: response['error_code'] as String? ?? 'STORE_CONTACT_FAILED',
      );
    }

    final data = response['data'];
    if (data is! Map<String, dynamic>) {
      throw ApiException(
        message: 'Could not load contact details',
        code: 'STORE_CONTACT_FAILED',
      );
    }

    return StoreContact.fromJson(data);
  }
}
