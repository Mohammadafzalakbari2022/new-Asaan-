import 'dart:io';

/// API Configuration
class ApiConfig {
  ApiConfig._();

  // Environment Configuration
  static const bool isProduction = true; // Change to true for production

  // Base URLs
  // iOS simulator: resolved via /etc/hosts on Mac → cartxis.test
  // Android emulator: 10.0.2.2 = host machine loopback; nginx vhost selected via Host header
  static const String _iosBaseUrl = 'https://cartxis.test';
  static const String _androidBaseUrl =
      'https://10.0.2.2'; // No path prefix — Host header routes to vhost root
  static const String productionBaseUrl = 'https://asaandotaf.store';

  // Sent as Host header on Android dev so nginx routes to the cartxis.test vhost
  static const String devDomain = 'cartxis.test';

  static String get testBaseUrl =>
      Platform.isAndroid ? _androidBaseUrl : _iosBaseUrl;

  // Current Base URL based on environment
  static String get baseUrl => isProduction ? productionBaseUrl : testBaseUrl;

  // API Version
  static const String apiVersion = 'v1';

  // API Endpoints
  static const String authLogin = '/api/$apiVersion/auth/login';
  static const String authRegister = '/api/$apiVersion/auth/register';
  static const String authLogout = '/api/$apiVersion/auth/logout';
  static const String authMe = '/api/$apiVersion/auth/me';
  static const String authForgotPassword =
      '/api/$apiVersion/auth/password/forgot';
  static const String authResetPassword =
      '/api/$apiVersion/auth/password/reset';
  static const String authVerifyEmail = '/api/$apiVersion/auth/verify-email';
  static const String authDeleteAccount = '/api/$apiVersion/auth/account';

  // Customer Endpoints
  static const String customerProfile = '/api/$apiVersion/customer/profile';
  static const String customerOrders = '/api/$apiVersion/customer/orders';
  static String customerOrderDetail(int orderId) =>
      '/api/$apiVersion/customer/orders/$orderId';
  static const String wishlist = '/api/$apiVersion/customer/wishlist';
  static const String wishlistAdd = '/api/$apiVersion/customer/wishlist/add';

  // Identity (Tazkira) Endpoints
  static const String identity = '/api/$apiVersion/identity';

  // Referral Endpoints
  static const String referrals = '/api/$apiVersion/referrals';
  static const String referralsLedger = '/api/$apiVersion/referrals/ledger';
  static const String referralsApply = '/api/$apiVersion/referrals/apply';

  // Currency Endpoints
  static const String currencyDefault = '/api/$apiVersion/currency/default';

  // System Sync Endpoints
  static const String apiSyncHeartbeat =
      '/api/$apiVersion/system/api-sync/heartbeat';
  static const String apiSyncStatus = '/api/$apiVersion/system/api-sync/status';

  // Service (bookings) Endpoints
  static const String services = '/api/$apiVersion/services';
  static const String serviceCategories =
      '/api/$apiVersion/services/categories';
  static String serviceDetail(String slug) => '/api/$apiVersion/services/$slug';
  static String serviceSlots(String slug) =>
      '/api/$apiVersion/services/$slug/slots';
  static String serviceBook(String slug) =>
      '/api/$apiVersion/services/$slug/book';

  // Localization Endpoints
  static const String locales = '/api/$apiVersion/locales';

  // Product Endpoints
  static const String products = '/api/$apiVersion/products';
  static const String productsFeatured = '/api/$apiVersion/products/featured';
  static const String productsNewArrivals =
      '/api/$apiVersion/products/new-arrivals';
  static const String productsOnSale = '/api/$apiVersion/products/on-sale';

  // Category Endpoints
  static const String categories = '/api/$apiVersion/categories';

  // Banner Endpoints
  static const String banners = '/api/$apiVersion/banners';

  // App Settings Endpoints
  static const String appSettings = '/api/$apiVersion/app/settings';

  // Storefront Endpoints
  static const String storeContact =
      '/api/$apiVersion/storefront/contact';

  // Public Android APK download on the live site
  static const String androidApkDownloadUrl =
      '$productionBaseUrl/downloads/asaan-2.5.6.apk';

  // Timeout Settings
  static const Duration connectionTimeout = Duration(seconds: 30);
  static const Duration receiveTimeout = Duration(seconds: 30);

  // Device Information
  static const String deviceName = 'Flutter App'; // Can be updated dynamically

  // Full URL Helper
  static String getFullUrl(String endpoint) {
    return '$baseUrl$endpoint';
  }
}
