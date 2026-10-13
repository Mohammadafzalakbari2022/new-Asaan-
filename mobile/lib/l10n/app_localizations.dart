import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:intl/intl.dart' as intl;

import 'app_localizations_en.dart';
import 'app_localizations_fa.dart';
import 'app_localizations_ps.dart';

// ignore_for_file: type=lint

/// Callers can lookup localized strings with an instance of AppLocalizations
/// returned by `AppLocalizations.of(context)`.
///
/// Applications need to include `AppLocalizations.delegate()` in their app's
/// `localizationDelegates` list, and the locales they support in the app's
/// `supportedLocales` list. For example:
///
/// ```dart
/// import 'l10n/app_localizations.dart';
///
/// return MaterialApp(
///   localizationsDelegates: AppLocalizations.localizationsDelegates,
///   supportedLocales: AppLocalizations.supportedLocales,
///   home: MyApplicationHome(),
/// );
/// ```
///
/// ## Update pubspec.yaml
///
/// Please make sure to update your pubspec.yaml to include the following
/// packages:
///
/// ```yaml
/// dependencies:
///   # Internationalization support.
///   flutter_localizations:
///     sdk: flutter
///   intl: any # Use the pinned version from flutter_localizations
///
///   # Rest of dependencies
/// ```
///
/// ## iOS Applications
///
/// iOS applications define key application metadata, including supported
/// locales, in an Info.plist file that is built into the application bundle.
/// To configure the locales supported by your app, you’ll need to edit this
/// file.
///
/// First, open your project’s ios/Runner.xcworkspace Xcode workspace file.
/// Then, in the Project Navigator, open the Info.plist file under the Runner
/// project’s Runner folder.
///
/// Next, select the Information Property List item, select Add Item from the
/// Editor menu, then select Localizations from the pop-up menu.
///
/// Select and expand the newly-created Localizations item then, for each
/// locale your application supports, add a new item and select the locale
/// you wish to add from the pop-up menu in the Value field. This list should
/// be consistent with the languages listed in the AppLocalizations.supportedLocales
/// property.
abstract class AppLocalizations {
  AppLocalizations(String locale)
      : localeName = intl.Intl.canonicalizedLocale(locale.toString());

  final String localeName;

  static AppLocalizations of(BuildContext context) {
    return Localizations.of<AppLocalizations>(context, AppLocalizations)!;
  }

  static const LocalizationsDelegate<AppLocalizations> delegate =
      _AppLocalizationsDelegate();

  /// A list of this localizations delegate along with the default localizations
  /// delegates.
  ///
  /// Returns a list of localizations delegates containing this delegate along with
  /// GlobalMaterialLocalizations.delegate, GlobalCupertinoLocalizations.delegate,
  /// and GlobalWidgetsLocalizations.delegate.
  ///
  /// Additional delegates can be added by appending to this list in
  /// MaterialApp. This list does not have to be used at all if a custom list
  /// of delegates is preferred or required.
  static const List<LocalizationsDelegate<dynamic>> localizationsDelegates =
      <LocalizationsDelegate<dynamic>>[
    delegate,
    GlobalMaterialLocalizations.delegate,
    GlobalCupertinoLocalizations.delegate,
    GlobalWidgetsLocalizations.delegate,
  ];

  /// A list of this localizations delegate's supported locales.
  static const List<Locale> supportedLocales = <Locale>[
    Locale('en'),
    Locale('fa'),
    Locale('ps')
  ];

  /// No description provided for @appTitle.
  ///
  /// In en, this message translates to:
  /// **'Asaan Store'**
  String get appTitle;

  /// No description provided for @navHome.
  ///
  /// In en, this message translates to:
  /// **'Home'**
  String get navHome;

  /// No description provided for @navCategories.
  ///
  /// In en, this message translates to:
  /// **'Categories'**
  String get navCategories;

  /// No description provided for @navCart.
  ///
  /// In en, this message translates to:
  /// **'Cart'**
  String get navCart;

  /// No description provided for @navWishlist.
  ///
  /// In en, this message translates to:
  /// **'Wishlist'**
  String get navWishlist;

  /// No description provided for @navProfile.
  ///
  /// In en, this message translates to:
  /// **'Profile'**
  String get navProfile;

  /// No description provided for @commonTryAgain.
  ///
  /// In en, this message translates to:
  /// **'Try again'**
  String get commonTryAgain;

  /// No description provided for @commonDone.
  ///
  /// In en, this message translates to:
  /// **'Done'**
  String get commonDone;

  /// No description provided for @commonCancel.
  ///
  /// In en, this message translates to:
  /// **'Cancel'**
  String get commonCancel;

  /// No description provided for @commonSave.
  ///
  /// In en, this message translates to:
  /// **'Save'**
  String get commonSave;

  /// No description provided for @commonDiscard.
  ///
  /// In en, this message translates to:
  /// **'DISCARD'**
  String get commonDiscard;

  /// No description provided for @commonKeepEditing.
  ///
  /// In en, this message translates to:
  /// **'KEEP EDITING'**
  String get commonKeepEditing;

  /// No description provided for @commonLanguage.
  ///
  /// In en, this message translates to:
  /// **'Language'**
  String get commonLanguage;

  /// No description provided for @languageTitle.
  ///
  /// In en, this message translates to:
  /// **'Language'**
  String get languageTitle;

  /// No description provided for @languageSubtitle.
  ///
  /// In en, this message translates to:
  /// **'Choose the language for the app.'**
  String get languageSubtitle;

  /// No description provided for @languageSystem.
  ///
  /// In en, this message translates to:
  /// **'System default'**
  String get languageSystem;

  /// No description provided for @languageLoadError.
  ///
  /// In en, this message translates to:
  /// **'Could not load the available languages.'**
  String get languageLoadError;

  /// No description provided for @servicesTitle.
  ///
  /// In en, this message translates to:
  /// **'Services'**
  String get servicesTitle;

  /// No description provided for @servicesSearchHint.
  ///
  /// In en, this message translates to:
  /// **'Search services'**
  String get servicesSearchHint;

  /// No description provided for @servicesAll.
  ///
  /// In en, this message translates to:
  /// **'All'**
  String get servicesAll;

  /// No description provided for @servicesEmpty.
  ///
  /// In en, this message translates to:
  /// **'No services match your search yet.'**
  String get servicesEmpty;

  /// No description provided for @servicesLoadError.
  ///
  /// In en, this message translates to:
  /// **'Could not load services. Please try again.'**
  String get servicesLoadError;

  /// No description provided for @servicesDetailsTitle.
  ///
  /// In en, this message translates to:
  /// **'Service details'**
  String get servicesDetailsTitle;

  /// No description provided for @servicesAbout.
  ///
  /// In en, this message translates to:
  /// **'About this service'**
  String get servicesAbout;

  /// No description provided for @servicesIncluded.
  ///
  /// In en, this message translates to:
  /// **'What is included'**
  String get servicesIncluded;

  /// No description provided for @servicesExcluded.
  ///
  /// In en, this message translates to:
  /// **'Not included'**
  String get servicesExcluded;

  /// No description provided for @servicesBooking.
  ///
  /// In en, this message translates to:
  /// **'Booking'**
  String get servicesBooking;

  /// No description provided for @servicesInfoOnly.
  ///
  /// In en, this message translates to:
  /// **'This service is shown for information only and cannot be booked right now.'**
  String get servicesInfoOnly;

  /// No description provided for @servicesBookButton.
  ///
  /// In en, this message translates to:
  /// **'Book this service'**
  String get servicesBookButton;

  /// No description provided for @servicesAlsoNeed.
  ///
  /// In en, this message translates to:
  /// **'You may also need'**
  String get servicesAlsoNeed;

  /// No description provided for @servicesFeatured.
  ///
  /// In en, this message translates to:
  /// **'Featured'**
  String get servicesFeatured;

  /// No description provided for @servicesDetailLoadError.
  ///
  /// In en, this message translates to:
  /// **'Could not load this service. Please try again.'**
  String get servicesDetailLoadError;

  /// No description provided for @bookingTitle.
  ///
  /// In en, this message translates to:
  /// **'Book a service'**
  String get bookingTitle;

  /// No description provided for @bookingIntro.
  ///
  /// In en, this message translates to:
  /// **'Tell us where to come and when. The shop will confirm shortly.'**
  String get bookingIntro;

  /// No description provided for @bookingName.
  ///
  /// In en, this message translates to:
  /// **'Your name'**
  String get bookingName;

  /// No description provided for @bookingNameRequired.
  ///
  /// In en, this message translates to:
  /// **'Tell us your name so we know who to ask for.'**
  String get bookingNameRequired;

  /// No description provided for @bookingPhone.
  ///
  /// In en, this message translates to:
  /// **'Phone number'**
  String get bookingPhone;

  /// No description provided for @bookingPhoneRequired.
  ///
  /// In en, this message translates to:
  /// **'A phone number is needed so the worker can call you.'**
  String get bookingPhoneRequired;

  /// No description provided for @bookingPhoneShort.
  ///
  /// In en, this message translates to:
  /// **'That phone number looks too short.'**
  String get bookingPhoneShort;

  /// No description provided for @bookingEmail.
  ///
  /// In en, this message translates to:
  /// **'Email (optional)'**
  String get bookingEmail;

  /// No description provided for @bookingEmailInvalid.
  ///
  /// In en, this message translates to:
  /// **'That email address is not valid.'**
  String get bookingEmailInvalid;

  /// No description provided for @bookingAddress.
  ///
  /// In en, this message translates to:
  /// **'Address where the work is needed'**
  String get bookingAddress;

  /// No description provided for @bookingAddressRequired.
  ///
  /// In en, this message translates to:
  /// **'Tell us the address where the work is needed.'**
  String get bookingAddressRequired;

  /// No description provided for @bookingAddressShort.
  ///
  /// In en, this message translates to:
  /// **'Please give the full address, including the area.'**
  String get bookingAddressShort;

  /// No description provided for @bookingCity.
  ///
  /// In en, this message translates to:
  /// **'City / district (optional)'**
  String get bookingCity;

  /// No description provided for @bookingDate.
  ///
  /// In en, this message translates to:
  /// **'Date'**
  String get bookingDate;

  /// No description provided for @bookingPickDate.
  ///
  /// In en, this message translates to:
  /// **'Choose a date'**
  String get bookingPickDate;

  /// No description provided for @bookingDateRequired.
  ///
  /// In en, this message translates to:
  /// **'Choose the day you would like the work done.'**
  String get bookingDateRequired;

  /// No description provided for @bookingTime.
  ///
  /// In en, this message translates to:
  /// **'Time of day'**
  String get bookingTime;

  /// No description provided for @bookingPickTime.
  ///
  /// In en, this message translates to:
  /// **'Choose a time'**
  String get bookingPickTime;

  /// No description provided for @bookingTimeRequired.
  ///
  /// In en, this message translates to:
  /// **'Choose a time of day.'**
  String get bookingTimeRequired;

  /// No description provided for @bookingNotes.
  ///
  /// In en, this message translates to:
  /// **'Anything else? (optional)'**
  String get bookingNotes;

  /// No description provided for @bookingSend.
  ///
  /// In en, this message translates to:
  /// **'Send booking'**
  String get bookingSend;

  /// No description provided for @bookingCalendarError.
  ///
  /// In en, this message translates to:
  /// **'Could not load the booking calendar.'**
  String get bookingCalendarError;

  /// No description provided for @bookingSendFailed.
  ///
  /// In en, this message translates to:
  /// **'Could not send your booking. Please try again.'**
  String get bookingSendFailed;

  /// No description provided for @bookingDiscardTitle.
  ///
  /// In en, this message translates to:
  /// **'Discard this booking?'**
  String get bookingDiscardTitle;

  /// No description provided for @bookingDiscardBody.
  ///
  /// In en, this message translates to:
  /// **'You have typed details that have not been sent yet. Leaving now will lose them.'**
  String get bookingDiscardBody;

  /// No description provided for @bookingSentTitle.
  ///
  /// In en, this message translates to:
  /// **'Booking sent'**
  String get bookingSentTitle;

  /// No description provided for @bookingSentBody.
  ///
  /// In en, this message translates to:
  /// **'The shop will confirm your booking shortly.'**
  String get bookingSentBody;

  /// No description provided for @bookingReference.
  ///
  /// In en, this message translates to:
  /// **'Reference'**
  String get bookingReference;

  /// No description provided for @bookingServiceLabel.
  ///
  /// In en, this message translates to:
  /// **'Service'**
  String get bookingServiceLabel;

  /// No description provided for @bookingPhoneLabel.
  ///
  /// In en, this message translates to:
  /// **'Phone'**
  String get bookingPhoneLabel;

  /// No description provided for @referralShareBody.
  ///
  /// In en, this message translates to:
  /// **'Share your link. When a friend joins and spends {threshold}, you earn {reward} in store credit. Credit unlocks after {days} days.'**
  String referralShareBody(String threshold, String reward, int days);

  /// No description provided for @referralSpentSoFar.
  ///
  /// In en, this message translates to:
  /// **'Spent {amount} so far'**
  String referralSpentSoFar(String amount);

  /// No description provided for @referralJoined.
  ///
  /// In en, this message translates to:
  /// **'joined {date}'**
  String referralJoined(String date);

  /// No description provided for @referralLockedUntil.
  ///
  /// In en, this message translates to:
  /// **'Locked until {date}'**
  String referralLockedUntil(String date);

  /// No description provided for @referralVerifiedOn.
  ///
  /// In en, this message translates to:
  /// **'Verified on {date}.'**
  String referralVerifiedOn(String date);

  /// No description provided for @referralSubmittedOn.
  ///
  /// In en, this message translates to:
  /// **'Submitted on {date}. Our team will check it shortly.'**
  String referralSubmittedOn(String date);

  /// No description provided for @identityAcceptedFormats.
  ///
  /// In en, this message translates to:
  /// **'Accepted formats: {types} · Maximum size: {max}'**
  String identityAcceptedFormats(String types, String max);

  /// No description provided for @profileHeaderAccount.
  ///
  /// In en, this message translates to:
  /// **'ACCOUNT'**
  String get profileHeaderAccount;

  /// No description provided for @profileHeaderSupport.
  ///
  /// In en, this message translates to:
  /// **'SUPPORT'**
  String get profileHeaderSupport;

  /// No description provided for @profileMyOrders.
  ///
  /// In en, this message translates to:
  /// **'My Orders'**
  String get profileMyOrders;

  /// No description provided for @profileAddresses.
  ///
  /// In en, this message translates to:
  /// **'Addresses'**
  String get profileAddresses;

  /// No description provided for @profileIdentity.
  ///
  /// In en, this message translates to:
  /// **'Identity Verification'**
  String get profileIdentity;

  /// No description provided for @profileReferrals.
  ///
  /// In en, this message translates to:
  /// **'Referrals'**
  String get profileReferrals;

  /// No description provided for @profileServices.
  ///
  /// In en, this message translates to:
  /// **'Services'**
  String get profileServices;

  /// No description provided for @profileHelpSupport.
  ///
  /// In en, this message translates to:
  /// **'Help & Support'**
  String get profileHelpSupport;

  /// No description provided for @profileChatWhatsApp.
  ///
  /// In en, this message translates to:
  /// **'Chat on WhatsApp'**
  String get profileChatWhatsApp;

  /// No description provided for @profileGetApp.
  ///
  /// In en, this message translates to:
  /// **'Get the Android App'**
  String get profileGetApp;

  /// No description provided for @profileAboutUs.
  ///
  /// In en, this message translates to:
  /// **'About Us'**
  String get profileAboutUs;

  /// No description provided for @profileTerms.
  ///
  /// In en, this message translates to:
  /// **'Terms & Conditions'**
  String get profileTerms;

  /// No description provided for @profilePrivacy.
  ///
  /// In en, this message translates to:
  /// **'Privacy Policy'**
  String get profilePrivacy;

  /// No description provided for @profileEditProfile.
  ///
  /// In en, this message translates to:
  /// **'Edit Profile'**
  String get profileEditProfile;

  /// No description provided for @referralsTitle.
  ///
  /// In en, this message translates to:
  /// **'Referrals'**
  String get referralsTitle;

  /// No description provided for @referralProgramOff.
  ///
  /// In en, this message translates to:
  /// **'The referral programme is currently switched off. You can still see your balance.'**
  String get referralProgramOff;

  /// No description provided for @referralCreditAvailable.
  ///
  /// In en, this message translates to:
  /// **'Credit available'**
  String get referralCreditAvailable;

  /// No description provided for @referralPending.
  ///
  /// In en, this message translates to:
  /// **'Pending'**
  String get referralPending;

  /// No description provided for @referralEarned.
  ///
  /// In en, this message translates to:
  /// **'Earned'**
  String get referralEarned;

  /// No description provided for @referralUsed.
  ///
  /// In en, this message translates to:
  /// **'Used'**
  String get referralUsed;

  /// No description provided for @referralYourCode.
  ///
  /// In en, this message translates to:
  /// **'Your referral code'**
  String get referralYourCode;

  /// No description provided for @referralCopyCode.
  ///
  /// In en, this message translates to:
  /// **'Copy code'**
  String get referralCopyCode;

  /// No description provided for @referralCopiedCode.
  ///
  /// In en, this message translates to:
  /// **'Code copied.'**
  String get referralCopiedCode;

  /// No description provided for @referralCopiedLink.
  ///
  /// In en, this message translates to:
  /// **'Link copied.'**
  String get referralCopiedLink;

  /// No description provided for @referralApplyTitle.
  ///
  /// In en, this message translates to:
  /// **'Enter a friend\'s code'**
  String get referralApplyTitle;

  /// No description provided for @referralApplyHint.
  ///
  /// In en, this message translates to:
  /// **'Enter the code a friend shared with you. It can only be used once, and only on an account that does not have one yet.'**
  String get referralApplyHint;

  /// No description provided for @referralApplyFieldHint.
  ///
  /// In en, this message translates to:
  /// **'e.g. AHMAD-7K2QX'**
  String get referralApplyFieldHint;

  /// No description provided for @referralApplyButton.
  ///
  /// In en, this message translates to:
  /// **'Apply code'**
  String get referralApplyButton;

  /// No description provided for @referralApplyFailed.
  ///
  /// In en, this message translates to:
  /// **'Could not apply the code. Please try again.'**
  String get referralApplyFailed;

  /// No description provided for @referralApplied.
  ///
  /// In en, this message translates to:
  /// **'Referral code applied.'**
  String get referralApplied;

  /// No description provided for @referralInvitedTitle.
  ///
  /// In en, this message translates to:
  /// **'Friends you invited'**
  String get referralInvitedTitle;

  /// No description provided for @referralInvitedEmpty.
  ///
  /// In en, this message translates to:
  /// **'Nobody yet. Share your link to get started.'**
  String get referralInvitedEmpty;

  /// No description provided for @referralRewarded.
  ///
  /// In en, this message translates to:
  /// **'Rewarded'**
  String get referralRewarded;

  /// No description provided for @referralVoided.
  ///
  /// In en, this message translates to:
  /// **'Voided'**
  String get referralVoided;

  /// No description provided for @referralRewardPending.
  ///
  /// In en, this message translates to:
  /// **'Reward pending'**
  String get referralRewardPending;

  /// No description provided for @referralHistoryTitle.
  ///
  /// In en, this message translates to:
  /// **'Credit history'**
  String get referralHistoryTitle;

  /// No description provided for @referralHistoryEmpty.
  ///
  /// In en, this message translates to:
  /// **'No credit activity yet.'**
  String get referralHistoryEmpty;

  /// No description provided for @referralDiscardTitle.
  ///
  /// In en, this message translates to:
  /// **'Discard your changes?'**
  String get referralDiscardTitle;

  /// No description provided for @referralDiscardBody.
  ///
  /// In en, this message translates to:
  /// **'The referral code you typed has not been applied.'**
  String get referralDiscardBody;

  /// No description provided for @referralLedgerCredit.
  ///
  /// In en, this message translates to:
  /// **'Credit'**
  String get referralLedgerCredit;

  /// No description provided for @referralLedgerReward.
  ///
  /// In en, this message translates to:
  /// **'Referral reward'**
  String get referralLedgerReward;

  /// No description provided for @referralLedgerAdminCredit.
  ///
  /// In en, this message translates to:
  /// **'Store credit added'**
  String get referralLedgerAdminCredit;

  /// No description provided for @referralLedgerAdminDebit.
  ///
  /// In en, this message translates to:
  /// **'Adjustment'**
  String get referralLedgerAdminDebit;

  /// No description provided for @identityTitle.
  ///
  /// In en, this message translates to:
  /// **'Identity Verification'**
  String get identityTitle;

  /// No description provided for @identityVerified.
  ///
  /// In en, this message translates to:
  /// **'Verified'**
  String get identityVerified;

  /// No description provided for @identityNotAccepted.
  ///
  /// In en, this message translates to:
  /// **'Not accepted'**
  String get identityNotAccepted;

  /// No description provided for @identityUnderReview.
  ///
  /// In en, this message translates to:
  /// **'Under review'**
  String get identityUnderReview;

  /// No description provided for @identityNotVerified.
  ///
  /// In en, this message translates to:
  /// **'Not verified yet'**
  String get identityNotVerified;

  /// No description provided for @identityVerifiedDefault.
  ///
  /// In en, this message translates to:
  /// **'Your identity has been verified.'**
  String get identityVerifiedDefault;

  /// No description provided for @identityRejectedDefault.
  ///
  /// In en, this message translates to:
  /// **'Your last submission was not accepted. You can try again.'**
  String get identityRejectedDefault;

  /// No description provided for @identityNotVerifiedBody.
  ///
  /// In en, this message translates to:
  /// **'Verify your Tazkira to unlock earning from referrals and to keep your account secure.'**
  String get identityNotVerifiedBody;

  /// No description provided for @identityVerifiedNotice.
  ///
  /// In en, this message translates to:
  /// **'Your identity is verified. Nothing more is needed.'**
  String get identityVerifiedNotice;

  /// No description provided for @identityPendingNotice.
  ///
  /// In en, this message translates to:
  /// **'You already have a Tazkira under review. We will let you know once it is checked.'**
  String get identityPendingNotice;

  /// No description provided for @identityNumberLabel.
  ///
  /// In en, this message translates to:
  /// **'Tazkira number'**
  String get identityNumberLabel;

  /// No description provided for @identityNumberHint.
  ///
  /// In en, this message translates to:
  /// **'Tazkira number'**
  String get identityNumberHint;

  /// No description provided for @identityNumberEmpty.
  ///
  /// In en, this message translates to:
  /// **'Enter your Tazkira number.'**
  String get identityNumberEmpty;

  /// No description provided for @identityNumberInvalid.
  ///
  /// In en, this message translates to:
  /// **'That Tazkira number does not look right. Type the digits as printed.'**
  String get identityNumberInvalid;

  /// No description provided for @identityFullNameLabel.
  ///
  /// In en, this message translates to:
  /// **'Full name (as on the Tazkira)'**
  String get identityFullNameLabel;

  /// No description provided for @identityFullNameHint.
  ///
  /// In en, this message translates to:
  /// **'Your full name'**
  String get identityFullNameHint;

  /// No description provided for @identityFullNameEmpty.
  ///
  /// In en, this message translates to:
  /// **'Enter your full name exactly as it is written on the Tazkira.'**
  String get identityFullNameEmpty;

  /// No description provided for @identityFatherNameLabel.
  ///
  /// In en, this message translates to:
  /// **'Father\'s name (optional)'**
  String get identityFatherNameLabel;

  /// No description provided for @identityFatherNameHint.
  ///
  /// In en, this message translates to:
  /// **'Father\'s name'**
  String get identityFatherNameHint;

  /// No description provided for @identityDateLabel.
  ///
  /// In en, this message translates to:
  /// **'Date of birth (optional)'**
  String get identityDateLabel;

  /// No description provided for @identitySelectDate.
  ///
  /// In en, this message translates to:
  /// **'Select date'**
  String get identitySelectDate;

  /// No description provided for @identityPhotoLabel.
  ///
  /// In en, this message translates to:
  /// **'Photo of your Tazkira'**
  String get identityPhotoLabel;

  /// No description provided for @identityPhotoTap.
  ///
  /// In en, this message translates to:
  /// **'Tap to add a photo'**
  String get identityPhotoTap;

  /// No description provided for @identityPhotoMissing.
  ///
  /// In en, this message translates to:
  /// **'Add a photo of your Tazkira so our team can check it.'**
  String get identityPhotoMissing;

  /// No description provided for @identitySubmit.
  ///
  /// In en, this message translates to:
  /// **'Submit for review'**
  String get identitySubmit;

  /// No description provided for @identitySubmitted.
  ///
  /// In en, this message translates to:
  /// **'Tazkira submitted. Our team will review it shortly.'**
  String get identitySubmitted;

  /// No description provided for @identityLoadError.
  ///
  /// In en, this message translates to:
  /// **'Could not load your verification status.'**
  String get identityLoadError;

  /// No description provided for @identitySubmitError.
  ///
  /// In en, this message translates to:
  /// **'Something went wrong. Please try again.'**
  String get identitySubmitError;

  /// No description provided for @identityDiscardTitle.
  ///
  /// In en, this message translates to:
  /// **'Discard your changes?'**
  String get identityDiscardTitle;

  /// No description provided for @identityDiscardBody.
  ///
  /// In en, this message translates to:
  /// **'You have typed information that has not been submitted yet.'**
  String get identityDiscardBody;

  /// No description provided for @identityPhotoError.
  ///
  /// In en, this message translates to:
  /// **'Could not open your photos.'**
  String get identityPhotoError;

  /// No description provided for @commonError.
  ///
  /// In en, this message translates to:
  /// **'Something went wrong. Please try again.'**
  String get commonError;

  /// No description provided for @commonShare.
  ///
  /// In en, this message translates to:
  /// **'Share'**
  String get commonShare;

  /// No description provided for @servicesContactCall.
  ///
  /// In en, this message translates to:
  /// **'Call {phone}'**
  String servicesContactCall(String phone);

  /// No description provided for @servicesContactWhatsapp.
  ///
  /// In en, this message translates to:
  /// **'WhatsApp {phone}'**
  String servicesContactWhatsapp(String phone);

  /// No description provided for @referralApplyEmpty.
  ///
  /// In en, this message translates to:
  /// **'Type the code you were given.'**
  String get referralApplyEmpty;

  /// No description provided for @referralHaveCode.
  ///
  /// In en, this message translates to:
  /// **'Have a referral code?'**
  String get referralHaveCode;

  /// No description provided for @referralJoinedWith.
  ///
  /// In en, this message translates to:
  /// **'You joined with {name}\'s referral code.'**
  String referralJoinedWith(String name);

  /// No description provided for @referralLedgerSpent.
  ///
  /// In en, this message translates to:
  /// **'Used on an order'**
  String get referralLedgerSpent;

  /// No description provided for @referralLedgerReversed.
  ///
  /// In en, this message translates to:
  /// **'Reversed'**
  String get referralLedgerReversed;

  /// No description provided for @identityVerifiedOn.
  ///
  /// In en, this message translates to:
  /// **'Verified on {date}.'**
  String identityVerifiedOn(String date);

  /// No description provided for @identitySubmittedOn.
  ///
  /// In en, this message translates to:
  /// **'Submitted on {date}. Our team will check it shortly.'**
  String identitySubmittedOn(String date);

  /// No description provided for @identityNumberExample.
  ///
  /// In en, this message translates to:
  /// **'e.g. 1401 5678 9012'**
  String get identityNumberExample;

  /// No description provided for @identityFullNameShort.
  ///
  /// In en, this message translates to:
  /// **'That name looks too short.'**
  String get identityFullNameShort;
}

class _AppLocalizationsDelegate
    extends LocalizationsDelegate<AppLocalizations> {
  const _AppLocalizationsDelegate();

  @override
  Future<AppLocalizations> load(Locale locale) {
    return SynchronousFuture<AppLocalizations>(lookupAppLocalizations(locale));
  }

  @override
  bool isSupported(Locale locale) =>
      <String>['en', 'fa', 'ps'].contains(locale.languageCode);

  @override
  bool shouldReload(_AppLocalizationsDelegate old) => false;
}

AppLocalizations lookupAppLocalizations(Locale locale) {
  // Lookup logic when only language code is specified.
  switch (locale.languageCode) {
    case 'en':
      return AppLocalizationsEn();
    case 'fa':
      return AppLocalizationsFa();
    case 'ps':
      return AppLocalizationsPs();
  }

  throw FlutterError(
      'AppLocalizations.delegate failed to load unsupported locale "$locale". This is likely '
      'an issue with the localizations generation tool. Please file an issue '
      'on GitHub with a reproducible sample app and the gen-l10n configuration '
      'that was used.');
}
