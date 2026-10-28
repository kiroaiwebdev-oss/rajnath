import 'package:flutter/material.dart';

/// Design tokens mirrored from backend/resources/css/app.css so web and app look the same.
abstract final class ArjColors {
  static const ivory50 = Color(0xFFFDFBF6);
  static const ivory100 = Color(0xFFF8F3E8);
  static const ivory200 = Color(0xFFEFE6D2);
  static const ink900 = Color(0xFF141A3C);
  static const ink700 = Color(0xFF2B356F);
  static const ink600 = Color(0xFF444D80);
  static const ink200 = Color(0xFFD7D9E8);
  static const gold50 = Color(0xFFFBF5E4);
  static const gold200 = Color(0xFFECD9A0);
  static const gold500 = Color(0xFFC79A2E);
  static const gold700 = Color(0xFF8A6514);
  static const danger700 = Color(0xFFA12A2A);
  static const success700 = Color(0xFF1F6B45);
}

ThemeData buildArjTheme() {
  const radius = BorderRadius.all(Radius.circular(12));
  final scheme = ColorScheme.fromSeed(
    seedColor: ArjColors.ink900,
    primary: ArjColors.ink900,
    onPrimary: Colors.white,
    secondary: ArjColors.gold500,
    onSecondary: ArjColors.ink900,
    surface: Colors.white,
    onSurface: ArjColors.ink900,
    error: ArjColors.danger700,
  );
  return ThemeData(
    useMaterial3: true,
    colorScheme: scheme,
    scaffoldBackgroundColor: ArjColors.ivory50,
    appBarTheme: const AppBarTheme(
      backgroundColor: ArjColors.ivory50,
      foregroundColor: ArjColors.ink900,
      elevation: 0,
      scrolledUnderElevation: 0.5,
    ),
    cardTheme: const CardThemeData(
      color: Colors.white,
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.all(Radius.circular(20)),
        side: BorderSide(color: ArjColors.ink200),
      ),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: ArjColors.ink900,
        foregroundColor: Colors.white,
        minimumSize: const Size(48, 48), // touch target
        shape: const StadiumBorder(),
        textStyle: const TextStyle(fontWeight: FontWeight.w600),
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: ArjColors.ink900,
        minimumSize: const Size(48, 48),
        side: const BorderSide(color: ArjColors.ink200),
        shape: const StadiumBorder(),
      ),
    ),
    inputDecorationTheme: const InputDecorationTheme(
      filled: true,
      fillColor: Colors.white,
      border: OutlineInputBorder(borderRadius: radius, borderSide: BorderSide(color: ArjColors.ink200)),
      enabledBorder: OutlineInputBorder(borderRadius: radius, borderSide: BorderSide(color: ArjColors.ink200)),
      focusedBorder: OutlineInputBorder(borderRadius: radius, borderSide: BorderSide(color: ArjColors.gold500, width: 2)),
      errorBorder: OutlineInputBorder(borderRadius: radius, borderSide: BorderSide(color: ArjColors.danger700)),
    ),
  );
}
