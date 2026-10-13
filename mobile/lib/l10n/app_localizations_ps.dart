// ignore: unused_import
import 'package:intl/intl.dart' as intl;
import 'app_localizations.dart';

// ignore_for_file: type=lint

/// The translations for Pushto Pashto (`ps`).
class AppLocalizationsPs extends AppLocalizations {
  AppLocalizationsPs([String locale = 'ps']) : super(locale);

  @override
  String get appTitle => 'آسان ستور';

  @override
  String get navHome => 'کور';

  @override
  String get navCategories => 'ډولونه';

  @override
  String get navCart => 'کارټ';

  @override
  String get navWishlist => 'خوښې';

  @override
  String get navProfile => 'پروفایل';

  @override
  String get commonTryAgain => 'بیا هڅه وکړئ';

  @override
  String get commonDone => 'شو';

  @override
  String get commonCancel => 'لغوه';

  @override
  String get commonSave => 'خوندي کړئ';

  @override
  String get commonDiscard => 'پریږدئ';

  @override
  String get commonKeepEditing => 'سمون ته دوام';

  @override
  String get commonLanguage => 'ژبه';

  @override
  String get languageTitle => 'ژبه';

  @override
  String get languageSubtitle => 'د اپلیکیشن لپاره ژبه وټاکئ.';

  @override
  String get languageSystem => 'د سیستم ډیفالټ';

  @override
  String get languageLoadError => 'د موجودو ژبو راوړل ناکام شول.';

  @override
  String get servicesTitle => 'خدمتونه';

  @override
  String get servicesSearchHint => 'د خدمتونو پلټنه';

  @override
  String get servicesAll => 'ټول';

  @override
  String get servicesEmpty => 'هیڅ خدمت ستاسو له پلټنې سره سمون نه لري.';

  @override
  String get servicesLoadError => 'خدمتونه راوړل نشول. بیا هڅه وکړئ.';

  @override
  String get servicesDetailsTitle => 'د خدمت جزئیات';

  @override
  String get servicesAbout => 'د دې خدمت په اړه';

  @override
  String get servicesIncluded => 'څه شامل دي';

  @override
  String get servicesExcluded => 'شامل نه دي';

  @override
  String get servicesBooking => 'بکنګ';

  @override
  String get servicesInfoOnly =>
      'دا خدمت یوازې د معلوماتو لپاره ښودل شوی او اوس نشي بک کېدای.';

  @override
  String get servicesBookButton => 'دا خدمت بک کړئ';

  @override
  String get servicesAlsoNeed => 'کیدای شي دا هم ورته اړتیا ولرئ';

  @override
  String get servicesFeatured => 'ځانګړی';

  @override
  String get servicesDetailLoadError => 'دا خدمت راوړل نشو. بیا هڅه وکړئ.';

  @override
  String get bookingTitle => 'یو خدمت بک کړئ';

  @override
  String get bookingIntro =>
      'موږ ته ووایاست چې چیرته او کله راشو. پلورنځی به ژر تایید کړي.';

  @override
  String get bookingName => 'ستاسو نوم';

  @override
  String get bookingNameRequired =>
      'ستاسو نوم ووایاست ترڅو پوه شو چې چا ته مراجعه وکړو.';

  @override
  String get bookingPhone => 'د تلیفون شمیره';

  @override
  String get bookingPhoneRequired =>
      'د تلیفون شمیره اړینه ده ترڅو کارګر تاسو ته زنګ ووهي.';

  @override
  String get bookingPhoneShort => 'دا د تلیفون شمیره ډیره لنډه ښکاري.';

  @override
  String get bookingEmail => 'ایمیل (اختیاري)';

  @override
  String get bookingEmailInvalid => 'دا ایمیل پته سمه نه ده.';

  @override
  String get bookingAddress => 'هغه پته چیرې چې کار ته اړتیا ده';

  @override
  String get bookingAddressRequired =>
      'موږ ته هغه پته ووایاست چیرې چې کار ته اړتیا ده.';

  @override
  String get bookingAddressShort => 'بشپړه پته ولیکئ، له سیمې سره.';

  @override
  String get bookingCity => 'ښار / ولسوالۍ (اختیاري)';

  @override
  String get bookingDate => 'نېټه';

  @override
  String get bookingPickDate => 'یوه نېټه وټاکئ';

  @override
  String get bookingDateRequired => 'هغه ورځ وټاکئ چې غواړئ کار وشي.';

  @override
  String get bookingTime => 'د ورځې وخت';

  @override
  String get bookingPickTime => 'یو وخت وټاکئ';

  @override
  String get bookingTimeRequired => 'د ورځې وخت وټاکئ.';

  @override
  String get bookingNotes => 'نور څه؟ (اختیاري)';

  @override
  String get bookingSend => 'بکنګ ولېږئ';

  @override
  String get bookingCalendarError => 'د بکنګ کلیندر راوړل نشو.';

  @override
  String get bookingSendFailed => 'ستاسو بکنګ ولېږل نشو. بیا هڅه وکړئ.';

  @override
  String get bookingDiscardTitle => 'دا بکنګ پرېږدئ؟';

  @override
  String get bookingDiscardBody =>
      'تاسو معلومات لیکلي چې نه دي لیږل شوي. اوس وتل به یې له لاسه ورکړي.';

  @override
  String get bookingSentTitle => 'بکنګ ولېږل شو';

  @override
  String get bookingSentBody => 'پلورنځی به ژر ستاسو بکنګ تایید کړي.';

  @override
  String get bookingReference => 'حواله';

  @override
  String get bookingServiceLabel => 'خدمت';

  @override
  String get bookingPhoneLabel => 'تلیفون';

  @override
  String referralShareBody(String threshold, String reward, int days) {
    return 'خپله لینک شریک کړئ. کله چې یو ملګری یوځای شي او $threshold ولګوي، تاسو $reward په دوکان کریډیټ ترلاسه کوئ. کریډیټ د $days ورځو وروسته ازاد کیږي.';
  }

  @override
  String referralSpentSoFar(String amount) {
    return 'تر دې دمه $amount لګولي';
  }

  @override
  String referralJoined(String date) {
    return 'یوځای شو $date';
  }

  @override
  String referralLockedUntil(String date) {
    return 'تر $date پورې بند';
  }

  @override
  String referralVerifiedOn(String date) {
    return 'په $date تایید شو.';
  }

  @override
  String referralSubmittedOn(String date) {
    return 'په $date وسپارل شو. زموږ ټیم به ژر یې وګوري.';
  }

  @override
  String identityAcceptedFormats(String types, String max) {
    return 'منل شوي فورمېټونه: $types · اعظمي اندازه: $max';
  }

  @override
  String get profileHeaderAccount => 'حساب';

  @override
  String get profileHeaderSupport => 'ملاتړ';

  @override
  String get profileMyOrders => 'زما سپارښتنې';

  @override
  String get profileAddresses => 'پتې';

  @override
  String get profileIdentity => 'د هویت تصدیق';

  @override
  String get profileReferrals => 'ریفرلونه';

  @override
  String get profileServices => 'خدمتونه';

  @override
  String get profileHelpSupport => 'مرسته او ملاتړ';

  @override
  String get profileChatWhatsApp => 'په واټساپ کې خبرې';

  @override
  String get profileGetApp => 'د اندرویډ اپلیکیشن ترلاسه کول';

  @override
  String get profileAboutUs => 'زموږ په اړه';

  @override
  String get profileTerms => 'شرطونه او مقررات';

  @override
  String get profilePrivacy => 'د محرمیت پالیسي';

  @override
  String get profileEditProfile => 'پروفایل سمول';

  @override
  String get referralsTitle => 'ریفرلونه';

  @override
  String get referralProgramOff =>
      'د ریفرل پروګرام اوس بند دی. تاسو لاهم خپل بیلانس لیدلی شئ.';

  @override
  String get referralCreditAvailable => 'شته کریډیټ';

  @override
  String get referralPending => 'په تمه';

  @override
  String get referralEarned => 'ترلاسه شوی';

  @override
  String get referralUsed => 'لګول شوی';

  @override
  String get referralYourCode => 'ستاسو د ریفرل کوډ';

  @override
  String get referralCopyCode => 'کوډ کاپي کړئ';

  @override
  String get referralCopiedCode => 'کوډ کاپي شو.';

  @override
  String get referralCopiedLink => 'لینک کاپي شو.';

  @override
  String get referralApplyTitle => 'د ملګري کوډ ولیکئ';

  @override
  String get referralApplyHint =>
      'هغه کوډ ولیکئ چې ملګري یې شریک کړی. یوازې یو ځل کارول کیږي، او یوازې په هغه حساب چې لا یې نه لري.';

  @override
  String get referralApplyFieldHint => 'لکه AHMAD-7K2QX';

  @override
  String get referralApplyButton => 'کوډ پلي کړئ';

  @override
  String get referralApplyFailed => 'کوډ پلي نشو. بیا هڅه وکړئ.';

  @override
  String get referralApplied => 'د ریفرل کوډ پلي شو.';

  @override
  String get referralInvitedTitle => 'هغه ملګري چې تاسو بللي';

  @override
  String get referralInvitedEmpty =>
      'لا هیڅوک نه. د پیل لپاره خپله لینک شریک کړئ.';

  @override
  String get referralRewarded => 'انعام شوی';

  @override
  String get referralVoided => 'باطل شوی';

  @override
  String get referralRewardPending => 'انعام په تمه';

  @override
  String get referralHistoryTitle => 'د کریډیټ تاریخ';

  @override
  String get referralHistoryEmpty => 'لا د کریډیټ فعالیت نشته.';

  @override
  String get referralDiscardTitle => 'خپل بدلونونه پرېږدئ؟';

  @override
  String get referralDiscardBody =>
      'هغه ریفرل کوډ چې تاسو لیکلی نه دی پلي شوی.';

  @override
  String get referralLedgerCredit => 'کریډیټ';

  @override
  String get referralLedgerReward => 'د ریفرل انعام';

  @override
  String get referralLedgerAdminCredit => 'د دوکان کریډیټ اضافه شو';

  @override
  String get referralLedgerAdminDebit => 'سمون';

  @override
  String get identityTitle => 'د هویت تصدیق';

  @override
  String get identityVerified => 'تایید شوی';

  @override
  String get identityNotAccepted => 'نه منل شوی';

  @override
  String get identityUnderReview => 'د بیاکتنې لاندې';

  @override
  String get identityNotVerified => 'لا ندی تایید شوی';

  @override
  String get identityVerifiedDefault => 'ستاسو هویت تایید شوی.';

  @override
  String get identityRejectedDefault =>
      'ستاسو وروستی سپارل نه دی منل شوی. بیا هڅه کولی شئ.';

  @override
  String get identityNotVerifiedBody =>
      'خپله تذکره تایید کړئ ترڅو د ریفرل څخه ګټه پورته کړئ او حساب مو خوندي وساتئ.';

  @override
  String get identityVerifiedNotice =>
      'ستاسو هویت تایید شوی. نور څه ته اړتیا نشته.';

  @override
  String get identityPendingNotice =>
      'تاسو لا دمخه یوه تذکره د بیاکتنې لاندې لرئ. کله چې وکتل شي، خبر به یې درکړو.';

  @override
  String get identityNumberLabel => 'د تذکرې شمیره';

  @override
  String get identityNumberHint => 'د تذکرې شمیره';

  @override
  String get identityNumberEmpty => 'خپله د تذکرې شمیره ولیکئ.';

  @override
  String get identityNumberInvalid =>
      'دا د تذکرې شمیره سمه نه ښکاري. عددونه لکه څنګه چې چاپ شوي ولیکئ.';

  @override
  String get identityFullNameLabel => 'بشپړ نوم (لکه په تذکره)';

  @override
  String get identityFullNameHint => 'ستاسو بشپړ نوم';

  @override
  String get identityFullNameEmpty =>
      'خپل بشپړ نوم دقیقاً لکه څنګه چې په تذکره لیکل شوی ولیکئ.';

  @override
  String get identityFatherNameLabel => 'د پلار نوم (اختیاري)';

  @override
  String get identityFatherNameHint => 'د پلار نوم';

  @override
  String get identityDateLabel => 'د زوکړې نېټه (اختیاري)';

  @override
  String get identitySelectDate => 'نېټه وټاکئ';

  @override
  String get identityPhotoLabel => 'د تذکرې عکس';

  @override
  String get identityPhotoTap => 'د عکس اضافه کولو لپاره ټک وکړئ';

  @override
  String get identityPhotoMissing =>
      'د تذکرې عکس اضافه کړئ ترڅو زموږ ټیم یې وګوري.';

  @override
  String get identitySubmit => 'د بیاکتنې لپاره ولیږئ';

  @override
  String get identitySubmitted => 'تذکره وسپارل شوه. زموږ ټیم به ژر یې وګوري.';

  @override
  String get identityLoadError => 'ستاسو د تصدیق حالت راوړل نشو.';

  @override
  String get identitySubmitError => 'یو څه غلط شو. بیا هڅه وکړئ.';

  @override
  String get identityDiscardTitle => 'خپل بدلونونه پرېږدئ؟';

  @override
  String get identityDiscardBody => 'تاسو معلومات لیکلي چې لا نه دي سپارل شوي.';

  @override
  String get identityPhotoError => 'ستاسو عکسونه خلاص نشول.';

  @override
  String get commonError => 'یو څه غلط شو. بیا هڅه وکړئ.';

  @override
  String get commonShare => 'شریک کړئ';

  @override
  String servicesContactCall(String phone) {
    return '$phone ته زنګ';
  }

  @override
  String servicesContactWhatsapp(String phone) {
    return 'واټس اپ $phone';
  }

  @override
  String get referralApplyEmpty => 'هغه کوډ ولیکئ چې تاسو ته درکړل شوی.';

  @override
  String get referralHaveCode => 'ریفرل کوډ لرئ؟';

  @override
  String referralJoinedWith(String name) {
    return 'تاسو د $name د ریفرل کوډ سره یوځای شوئ.';
  }

  @override
  String get referralLedgerSpent => 'په یوه سپارښتنه کې لګول شوی';

  @override
  String get referralLedgerReversed => 'بیرته ګرځول شوی';

  @override
  String identityVerifiedOn(String date) {
    return 'په $date تایید شو.';
  }

  @override
  String identitySubmittedOn(String date) {
    return 'په $date وسپارل شو. زموږ ټیم به ژر یې وګوري.';
  }

  @override
  String get identityNumberExample => 'لکه ۱۴۰۱ ۵۶۷۸ ۹۰۱۲';

  @override
  String get identityFullNameShort => 'دا نوم ډیر لنډ ښکاري.';
}
