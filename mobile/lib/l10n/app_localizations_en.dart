// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for English (`en`).
class AppLocalizationsEn extends AppLocalizations {
  AppLocalizationsEn([String locale = 'en']) : super(locale);

  @override
  String get appTitle => 'Asaan Store';

  @override
  String get navHome => 'Home';

  @override
  String get navCategories => 'Categories';

  @override
  String get navCart => 'Cart';

  @override
  String get navWishlist => 'Wishlist';

  @override
  String get navProfile => 'Profile';

  @override
  String get commonTryAgain => 'Try again';

  @override
  String get commonDone => 'Done';

  @override
  String get commonCancel => 'Cancel';

  @override
  String get commonSave => 'Save';

  @override
  String get commonDiscard => 'DISCARD';

  @override
  String get commonKeepEditing => 'KEEP EDITING';

  @override
  String get commonLanguage => 'Language';

  @override
  String get languageTitle => 'Language';

  @override
  String get languageSubtitle => 'Choose the language for the app.';

  @override
  String get languageSystem => 'System default';

  @override
  String get languageLoadError => 'Could not load the available languages.';

  @override
  String get servicesTitle => 'Services';

  @override
  String get servicesSearchHint => 'Search services';

  @override
  String get servicesAll => 'All';

  @override
  String get servicesEmpty => 'No services match your search yet.';

  @override
  String get servicesLoadError => 'Could not load services. Please try again.';

  @override
  String get servicesDetailsTitle => 'Service details';

  @override
  String get servicesAbout => 'About this service';

  @override
  String get servicesIncluded => 'What is included';

  @override
  String get servicesExcluded => 'Not included';

  @override
  String get servicesBooking => 'Booking';

  @override
  String get servicesInfoOnly =>
      'This service is shown for information only and cannot be booked right now.';

  @override
  String get servicesBookButton => 'Book this service';

  @override
  String get servicesAlsoNeed => 'You may also need';

  @override
  String get servicesFeatured => 'Featured';

  @override
  String get servicesDetailLoadError =>
      'Could not load this service. Please try again.';

  @override
  String get bookingTitle => 'Book a service';

  @override
  String get bookingIntro =>
      'Tell us where to come and when. The shop will confirm shortly.';

  @override
  String get bookingName => 'Your name';

  @override
  String get bookingNameRequired =>
      'Tell us your name so we know who to ask for.';

  @override
  String get bookingPhone => 'Phone number';

  @override
  String get bookingPhoneRequired =>
      'A phone number is needed so the worker can call you.';

  @override
  String get bookingPhoneShort => 'That phone number looks too short.';

  @override
  String get bookingEmail => 'Email (optional)';

  @override
  String get bookingEmailInvalid => 'That email address is not valid.';

  @override
  String get bookingAddress => 'Address where the work is needed';

  @override
  String get bookingAddressRequired =>
      'Tell us the address where the work is needed.';

  @override
  String get bookingAddressShort =>
      'Please give the full address, including the area.';

  @override
  String get bookingCity => 'City / district (optional)';

  @override
  String get bookingDate => 'Date';

  @override
  String get bookingPickDate => 'Choose a date';

  @override
  String get bookingDateRequired =>
      'Choose the day you would like the work done.';

  @override
  String get bookingTime => 'Time of day';

  @override
  String get bookingPickTime => 'Choose a time';

  @override
  String get bookingTimeRequired => 'Choose a time of day.';

  @override
  String get bookingNotes => 'Anything else? (optional)';

  @override
  String get bookingSend => 'Send booking';

  @override
  String get bookingCalendarError => 'Could not load the booking calendar.';

  @override
  String get bookingSendFailed =>
      'Could not send your booking. Please try again.';

  @override
  String get bookingDiscardTitle => 'Discard this booking?';

  @override
  String get bookingDiscardBody =>
      'You have typed details that have not been sent yet. Leaving now will lose them.';

  @override
  String get bookingSentTitle => 'Booking sent';

  @override
  String get bookingSentBody => 'The shop will confirm your booking shortly.';

  @override
  String get bookingReference => 'Reference';

  @override
  String get bookingServiceLabel => 'Service';

  @override
  String get bookingPhoneLabel => 'Phone';

  @override
  String referralShareBody(String threshold, String reward, int days) {
    return 'Share your link. When a friend joins and spends $threshold, you earn $reward in store credit. Credit unlocks after $days days.';
  }

  @override
  String referralSpentSoFar(String amount) {
    return 'Spent $amount so far';
  }

  @override
  String referralJoined(String date) {
    return 'joined $date';
  }

  @override
  String referralLockedUntil(String date) {
    return 'Locked until $date';
  }

  @override
  String referralVerifiedOn(String date) {
    return 'Verified on $date.';
  }

  @override
  String referralSubmittedOn(String date) {
    return 'Submitted on $date. Our team will check it shortly.';
  }

  @override
  String identityAcceptedFormats(String types, String max) {
    return 'Accepted formats: $types · Maximum size: $max';
  }

  @override
  String get profileHeaderAccount => 'ACCOUNT';

  @override
  String get profileHeaderSupport => 'SUPPORT';

  @override
  String get profileMyOrders => 'My Orders';

  @override
  String get profileAddresses => 'Addresses';

  @override
  String get profileIdentity => 'Identity Verification';

  @override
  String get profileReferrals => 'Referrals';

  @override
  String get profileServices => 'Services';

  @override
  String get profileHelpSupport => 'Help & Support';

  @override
  String get profileChatWhatsApp => 'Chat on WhatsApp';

  @override
  String get profileGetApp => 'Get the Android App';

  @override
  String get profileAboutUs => 'About Us';

  @override
  String get profileTerms => 'Terms & Conditions';

  @override
  String get profilePrivacy => 'Privacy Policy';

  @override
  String get profileEditProfile => 'Edit Profile';

  @override
  String get referralsTitle => 'Referrals';

  @override
  String get referralProgramOff =>
      'The referral programme is currently switched off. You can still see your balance.';

  @override
  String get referralCreditAvailable => 'Credit available';

  @override
  String get referralPending => 'Pending';

  @override
  String get referralEarned => 'Earned';

  @override
  String get referralUsed => 'Used';

  @override
  String get referralYourCode => 'Your referral code';

  @override
  String get referralCopyCode => 'Copy code';

  @override
  String get referralCopiedCode => 'Code copied.';

  @override
  String get referralCopiedLink => 'Link copied.';

  @override
  String get referralApplyTitle => 'Enter a friend\'s code';

  @override
  String get referralApplyHint =>
      'Enter the code a friend shared with you. It can only be used once, and only on an account that does not have one yet.';

  @override
  String get referralApplyFieldHint => 'e.g. AHMAD-7K2QX';

  @override
  String get referralApplyButton => 'Apply code';

  @override
  String get referralApplyFailed =>
      'Could not apply the code. Please try again.';

  @override
  String get referralApplied => 'Referral code applied.';

  @override
  String get referralInvitedTitle => 'Friends you invited';

  @override
  String get referralInvitedEmpty =>
      'Nobody yet. Share your link to get started.';

  @override
  String get referralRewarded => 'Rewarded';

  @override
  String get referralVoided => 'Voided';

  @override
  String get referralRewardPending => 'Reward pending';

  @override
  String get referralHistoryTitle => 'Credit history';

  @override
  String get referralHistoryEmpty => 'No credit activity yet.';

  @override
  String get referralDiscardTitle => 'Discard your changes?';

  @override
  String get referralDiscardBody =>
      'The referral code you typed has not been applied.';

  @override
  String get referralLedgerCredit => 'Credit';

  @override
  String get referralLedgerReward => 'Referral reward';

  @override
  String get referralLedgerAdminCredit => 'Store credit added';

  @override
  String get referralLedgerAdminDebit => 'Adjustment';

  @override
  String get identityTitle => 'Identity Verification';

  @override
  String get identityVerified => 'Verified';

  @override
  String get identityNotAccepted => 'Not accepted';

  @override
  String get identityUnderReview => 'Under review';

  @override
  String get identityNotVerified => 'Not verified yet';

  @override
  String get identityVerifiedDefault => 'Your identity has been verified.';

  @override
  String get identityRejectedDefault =>
      'Your last submission was not accepted. You can try again.';

  @override
  String get identityNotVerifiedBody =>
      'Verify your Tazkira to unlock earning from referrals and to keep your account secure.';

  @override
  String get identityVerifiedNotice =>
      'Your identity is verified. Nothing more is needed.';

  @override
  String get identityPendingNotice =>
      'You already have a Tazkira under review. We will let you know once it is checked.';

  @override
  String get identityNumberLabel => 'Tazkira number';

  @override
  String get identityNumberHint => 'Tazkira number';

  @override
  String get identityNumberEmpty => 'Enter your Tazkira number.';

  @override
  String get identityNumberInvalid =>
      'That Tazkira number does not look right. Type the digits as printed.';

  @override
  String get identityFullNameLabel => 'Full name (as on the Tazkira)';

  @override
  String get identityFullNameHint => 'Your full name';

  @override
  String get identityFullNameEmpty =>
      'Enter your full name exactly as it is written on the Tazkira.';

  @override
  String get identityFatherNameLabel => 'Father\'s name (optional)';

  @override
  String get identityFatherNameHint => 'Father\'s name';

  @override
  String get identityDateLabel => 'Date of birth (optional)';

  @override
  String get identitySelectDate => 'Select date';

  @override
  String get identityPhotoLabel => 'Photo of your Tazkira';

  @override
  String get identityPhotoTap => 'Tap to add a photo';

  @override
  String get identityPhotoMissing =>
      'Add a photo of your Tazkira so our team can check it.';

  @override
  String get identitySubmit => 'Submit for review';

  @override
  String get identitySubmitted =>
      'Tazkira submitted. Our team will review it shortly.';

  @override
  String get identityLoadError => 'Could not load your verification status.';

  @override
  String get identitySubmitError => 'Something went wrong. Please try again.';

  @override
  String get identityDiscardTitle => 'Discard your changes?';

  @override
  String get identityDiscardBody =>
      'You have typed information that has not been submitted yet.';

  @override
  String get identityPhotoError => 'Could not open your photos.';

  @override
  String get commonError => 'Something went wrong. Please try again.';

  @override
  String get commonShare => 'Share';

  @override
  String servicesContactCall(String phone) {
    return 'Call $phone';
  }

  @override
  String servicesContactWhatsapp(String phone) {
    return 'WhatsApp $phone';
  }

  @override
  String get referralApplyEmpty => 'Type the code you were given.';

  @override
  String get referralHaveCode => 'Have a referral code?';

  @override
  String referralJoinedWith(String name) {
    return 'You joined with $name\'s referral code.';
  }

  @override
  String get referralLedgerSpent => 'Used on an order';

  @override
  String get referralLedgerReversed => 'Reversed';

  @override
  String identityVerifiedOn(String date) {
    return 'Verified on $date.';
  }

  @override
  String identitySubmittedOn(String date) {
    return 'Submitted on $date. Our team will check it shortly.';
  }

  @override
  String get identityNumberExample => 'e.g. 1401 5678 9012';

  @override
  String get identityFullNameShort => 'That name looks too short.';
}
