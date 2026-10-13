import 'package:flutter/material.dart';
import '../../../core/constants/app_colors.dart';
import '../../../core/constants/app_strings.dart';
import '../../../core/utils/whatsapp_utils.dart';
import '../../../data/models/store_contact_model.dart';
import '../../../data/services/store_contact_service.dart';

class HelpSupportScreen extends StatefulWidget {
  const HelpSupportScreen({super.key});

  @override
  State<HelpSupportScreen> createState() => _HelpSupportScreenState();
}

class _HelpSupportScreenState extends State<HelpSupportScreen> {
  final StoreContactService _contactService = StoreContactService();

  StoreContact? _contact;
  bool _isLoading = true;
  bool _hasError = false;

  @override
  void initState() {
    super.initState();
    _loadContact();
  }

  Future<void> _loadContact() async {
    setState(() {
      _isLoading = true;
      _hasError = false;
    });

    try {
      final contact = await _contactService.fetchContact();
      if (!mounted) return;
      setState(() {
        _contact = contact;
        _isLoading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _isLoading = false;
        _hasError = true;
      });
    }
  }

  Future<void> _chatOnWhatsApp() async {
    final contact = _contact;
    if (contact == null || !contact.hasWhatsapp) {
      _showMessage('WhatsApp is not available right now.');
      return;
    }

    final url = whatsAppUrl(
      contact.whatsapp,
      message: 'Hello ${AppStrings.appName}, I need help with my order.',
    );
    if (url.isEmpty) {
      _showMessage('WhatsApp is not available right now.');
      return;
    }
    await openUrl(url);
  }

  Future<void> _callPhone(String phone) async {
    await openUrl(phoneUrl(phone));
  }

  Future<void> _sendEmail(String email) async {
    await openUrl(emailUrl(email));
  }

  void _showMessage(String text) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(text), behavior: SnackBarBehavior.floating),
    );
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor: isDark ? const Color(0xFF101922) : const Color(0xFFF6F7F8),
      body: SafeArea(
        child: Column(
          children: [
            // Header
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
              decoration: BoxDecoration(
                color: isDark
                    ? const Color(0xFF101922).withOpacity(0.95)
                    : const Color(0xFFF6F7F8).withOpacity(0.95),
                border: Border(
                  bottom: BorderSide(
                    color: isDark
                        ? const Color(0xFF1F2937)
                        : const Color(0xFFE5E7EB),
                    width: 1,
                  ),
                ),
              ),
              child: Row(
                children: [
                  SizedBox(
                    width: 50,
                    child: IconButton(
                      onPressed: () => Navigator.pop(context),
                      icon: Icon(
                        Icons.arrow_back_ios_new,
                        color: isDark ? Colors.white : const Color(0xFF1F2937),
                      ),
                      padding: EdgeInsets.zero,
                    ),
                  ),
                  const Expanded(
                    child: Text(
                      'Help & Support',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                  const SizedBox(width: 50),
                ],
              ),
            ),

            // Content
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // FAQ Section
                    _buildSectionTitle('Frequently Asked Questions', isDark),
                    const SizedBox(height: 16),

                    _buildFAQItem(
                      'How do I track my order?',
                      'You can track your order by going to Profile > My Orders and selecting the order you want to track. Click on "Track Order" to see the current status and location.',
                      isDark,
                    ),

                    _buildFAQItem(
                      'What payment methods do you accept?',
                      'We accept Credit/Debit Cards (Visa, Mastercard, American Express), PayPal, Apple Pay, Google Pay, and Bank Transfer.',
                      isDark,
                    ),

                    _buildFAQItem(
                      'How long does shipping take?',
                      'Standard shipping takes 3-5 business days, Express shipping takes 1-2 business days, and Same Day delivery is available for select locations.',
                      isDark,
                    ),

                    _buildFAQItem(
                      'What is your return policy?',
                      'We offer a 30-day return policy. Items must be unused and in original packaging. Contact support to initiate a return.',
                      isDark,
                    ),

                    _buildFAQItem(
                      'How do I change my password?',
                      'Go to Profile > Edit Profile, scroll down to the Change Password section, and enter your current and new password.',
                      isDark,
                    ),

                    const SizedBox(height: 32),

                    // Contact Section
                    _buildSectionTitle('Contact Us', isDark),
                    const SizedBox(height: 16),

                    _buildContactSection(isDark),

                    const SizedBox(height: 24),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildContactSection(bool isDark) {
    if (_isLoading) {
      return Container(
        padding: const EdgeInsets.all(24),
        alignment: Alignment.center,
        child: const SizedBox(
          width: 24,
          height: 24,
          child: CircularProgressIndicator(strokeWidth: 2),
        ),
      );
    }

    final contact = _contact;
    if (_hasError || contact == null) {
      return _buildFallback(isDark);
    }

    final hasAny = contact.hasWhatsapp || contact.hasPhone || contact.hasEmail;
    if (!hasAny) {
      return _buildFallback(isDark);
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        if (contact.hasWhatsapp) ...[
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: _chatOnWhatsApp,
              icon: const Icon(Icons.chat_bubble_outline),
              label: const Text('Chat on WhatsApp'),
              style: ElevatedButton.styleFrom(
                backgroundColor: const Color(0xFF25D366),
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 16),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
            ),
          ),
          const SizedBox(height: 16),
        ],
        if (contact.hasPhone) ...[
          _buildContactCard(
            icon: Icons.phone_outlined,
            title: 'Phone',
            content: contact.phone,
            isDark: isDark,
            onTap: () => _callPhone(contact.phone),
          ),
          const SizedBox(height: 12),
        ],
        if (contact.hasEmail) ...[
          _buildContactCard(
            icon: Icons.email_outlined,
            title: 'Email',
            content: contact.email,
            isDark: isDark,
            onTap: () => _sendEmail(contact.email),
          ),
        ],
      ],
    );
  }

  Widget _buildFallback(bool isDark) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1F2937) : Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: isDark ? const Color(0xFF374151) : const Color(0xFFE5E7EB),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Our contact details could not be loaded. Please check your internet connection and try again.',
            style: TextStyle(
              fontSize: 14,
              height: 1.5,
              color: isDark ? const Color(0xFF9CA3AF) : const Color(0xFF6B7280),
            ),
          ),
          const SizedBox(height: 12),
          Align(
            alignment: Alignment.centerRight,
            child: TextButton.icon(
              onPressed: _loadContact,
              icon: const Icon(Icons.refresh),
              label: const Text('Try again'),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSectionTitle(String title, bool isDark) {
    return Text(
      title,
      style: TextStyle(
        fontSize: 20,
        fontWeight: FontWeight.bold,
        color: isDark ? Colors.white : const Color(0xFF0D141B),
      ),
    );
  }

  Widget _buildFAQItem(String question, String answer, bool isDark) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1F2937) : Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: isDark ? const Color(0xFF374151) : const Color(0xFFE5E7EB),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            question,
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w600,
              color: isDark ? Colors.white : const Color(0xFF0D141B),
            ),
          ),
          const SizedBox(height: 8),
          Text(
            answer,
            style: TextStyle(
              fontSize: 14,
              height: 1.5,
              color: isDark ? const Color(0xFF9CA3AF) : const Color(0xFF6B7280),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildContactCard({
    required IconData icon,
    required String title,
    required String content,
    required bool isDark,
    VoidCallback? onTap,
  }) {
    final card = Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: isDark ? const Color(0xFF1F2937) : Colors.white,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: isDark ? const Color(0xFF374151) : const Color(0xFFE5E7EB),
        ),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: AppColors.primary.withOpacity(0.1),
              shape: BoxShape.circle,
            ),
            child: Icon(
              icon,
              color: AppColors.primary,
              size: 24,
            ),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w500,
                    color: isDark
                        ? const Color(0xFF9CA3AF)
                        : const Color(0xFF6B7280),
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  content,
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                    color: AppColors.primary,
                  ),
                ),
              ],
            ),
          ),
          if (onTap != null)
            Icon(
              Icons.open_in_new,
              size: 18,
              color: isDark ? Colors.grey.shade600 : Colors.grey.shade400,
            ),
        ],
      ),
    );

    if (onTap == null) return card;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: card,
    );
  }
}
