# Astronomy and calculation methods

## Engine
- **Swiss Ephemeris 2.10.03** (Astrodienst; AGPL-3.0 option), compiled into `engine/bin/arj-ephem` (`engine/arj-ephem.c`, `engine/build.sh`).
- Ephemeris files `sepl_18`, `semo_18`, `seas_18`: supported range **1800–2399 CE**. Dates outside the range are rejected by validation. If the files are missing, the library falls back to Moshier, and the result is labelled "lower precision".
- **Zodiac:** sidereal, **Lahiri (Chitrapaksha)** ayanamsha (`SE_SIDM_LAHIRI`).
- **Nodes:** mean node by default (true node is available in the engine). Ketu = Rahu + 180°.
- **Houses:** whole-sign from the sidereal Lagna (ascendant computed with `swe_houses_ex`).
- **Time:** birth wall time + IANA zone → UTC with historical offsets (`LocalTimeResolver`).
  - DST overlaps: customer chooses the first or second occurrence.
  - DST gaps: shifted forward and flagged.
- **Invalid coordinates** are rejected by the engine (exit code 2), and the user sees a friendly error.

## Validated fixtures (`tests/Feature/Astro/EngineFixturesTest.php`)
- Sidereal positions of all grahas at J2000 (2000-01-01 12:00 UT) match `swetest -sid1` to 1e-6°. Ayanamsha ≈ 23.857°.
- 2025 sign changes (IST date):
  - Saturn → Meena on 29 Mar
  - Jupiter → Mithuna on 14 May
  - mean Rahu → Kumbha on 18 May (retrograde)

  These match widely published Lahiri ephemerides.
- Vimshottari: 120-year total, contiguous periods, correct starting lord and balance.
- Panchang for Patna, 28 Oct 2026: sunrise between 05:45 and 06:05 IST, Wednesday, Rahu Kaal = 5th eighth of daytime.
  At Longyearbyen on 21 Jun 2026 there is no sunrise (midnight sun): polar handling is used and no day-part intervals are shown.

## Panchang
- Vedic day = sunrise to next sunrise. Sunrise and sunset are the Sun's upper limb with standard refraction (`swe_rise_trans`).
- Angas:
  - tithi = (Moon − Sun) / 12°
  - nakshatra = Moon / 13°20′
  - yoga = (Moon + Sun) / 13°20′
  - karana = (Moon − Sun) / 6° (fixed and movable karanas)
  - pada = Moon / 3°20′
- Transitions are found by a 30-minute scan plus bisection to under 0.01 s. Times are shown in the place's local time. A transition after midnight shows its date.
- Intervals:
  - Rahu Kaal, Yamaganda and Gulika use the eight-part daytime division with the standard weekday tables.
  - Abhijit = 8th of 15 day muhurtas.
  - Brahma muhurta = 96 to 48 minutes before sunrise.
- **Choghadiya:** day (sunrise→sunset) and night (sunset→next sunrise) each in 8 equal parts. Cycle Udveg, Char, Labh, Amrit, Kaal, Shubh, Rog; the day starts with the weekday's entry (Sun Udveg, Mon Amrit, Tue Rog, Wed Labh, Thu Shubh, Fri Char, Sat Kaal) and steps +1, the night starts Sun Shubh, Mon Char, Tue Kaal, Wed Udveg, Thu Amrit, Fri Rog, Sat Labh and steps −2. Amrit, Shubh, Labh good; Char neutral; Udveg, Rog, Kaal avoided.
- **Hora:** 12 day and 12 night planetary hours (1/12 of each); the first day hora belongs to the weekday lord and each next one follows the Chaldean order Saturn, Jupiter, Mars, Sun, Venus, Mercury, Moon (so the next sunrise starts with the next weekday's lord).
- Lunar months are shown in both systems: amanta and purnimanta (see below).

## Lunar months (`LunarCalendar`)
- New and full moons come from the engine (`lunations`: Moon − Sun elongation crossing 0° / 180°, 12-hour scan plus bisection). Checked against published times: full moon 3 Jan 2026 10:03 UT, new moon 18 Jan 2026 19:52 UT.
- **Amanta** months run new moon to new moon. The name comes from the Sun's sidereal sign at the starting new moon: Sun in Meena → Chaitra, in Mesha → Vaishakha, and so on.
- A month in which the Sun does not change sign is **Adhik** and takes the following month's name (e.g. Adhik Jyeshtha, 17 May–15 Jun 2026). Kshaya masa is not handled.
- **Purnimanta** months (used in North India) run full moon to full moon. The Shukla paksha has the same name as amanta; the Krishna paksha (tithi 16–30) takes the next month's name. Example: 29 Oct 2026, Krishna Chaturthi, is Ashwin (amanta) and Kartik (purnimanta). Adhik months keep the amanta name in both systems.

## Festivals (`Festivals`, `/festivals`, monthly `/calendar`)
- Lunar festivals: amanta month + tithi (rule table in `Festivals::LUNAR`). Adhik months are skipped. The festival falls on the local day whose observance time lies in the tithi:
  - sunrise (udaya), purvahna (first third of the day), madhyahna (2/5–3/5 of the day), aparahna (3/5–4/5), pradosh (sunset + 2 h 24 min), nishita (24 min each side of the middle of the night) or moonrise.
  - Window rules take the day with the largest overlap; instant rules take the first day. If the tithi misses the time on every day (kshaya), the day the tithi begins is used.
- Holika Dahan: Purnima in pradosh, avoiding Bhadra (first half of Purnima). If Bhadra covers pradosh but ends before the middle of the night, the dahan is that night after Bhadra. If Bhadra lasts past midnight and Purnima still holds at the next sunrise, it moves to the next evening. Holi is the following day.
- Makar and Mesha Sankranti: the Sun's sidereal ingress. After local sunset → next day. Lohri is the day before Makar Sankranti; Vaisakhi is Mesha Sankranti.
- Derived dates: Holi, Hola Mohalla and Paryushan start are offsets from Holika Dahan and Samvatsari.
- Christian: Gregorian Easter (anonymous Gregorian computus) ± fixed offsets; Christmas 25 Dec.
- Islamic: Umm al-Qura calendar via ICU (`islamic-umalqura`). Indian observance often falls one day later after local moon sighting; the page says so.
- Spot checks for Patna, as published by major panchangs:
  - 2025: Diwali 20 Oct, Chhath (Sandhya Arghya) 27 Oct, Karwa Chauth 10 Oct, Raksha Bandhan 9 Aug, Holi 14 Mar, Vasant Panchami 2 Feb, Vat Savitri 26 May.
  - 2026 (checked against published 2026 calendars, Oct 2026): Holi 4 Mar, Ugadi/Chaitra Navratri 19 Mar, Ram Navami 26 Mar, Ganesh Chaturthi 14 Sep, Sharad Navratri 11 Oct, Diwali 8 Nov, Kartik Purnima 24 Nov. Sources disagree on Akshaya Tritiya (we give 20 Apr).
  - 2024: Holi 25 Mar.
- **Not modelled:** Smarta/Vaishnava differences (e.g. Janmashtami), Bhadra for Raksha Bandhan, dashami-viddha Ekadashi, regional calendars. The page tells readers that some dates can differ by a day.

## Muhurat (`Muhurat`, `/muhurat/{event}`), rule set v1
- Each Vedic day (sunrise to next sunrise) is cut at every tithi, nakshatra, yoga and karana change. A window is a stretch of at least 48 minutes in which every rule holds. Weekday is the sunrise weekday.
- Every event avoids: Amavasya, Vyatipata and Vaidhriti yoga, and Vishti (Bhadra) karana.
- Per-event tithi, vara and nakshatra lists are in `Muhurat::EVENTS`. These are the common Muhurta Chintamani lists.
- Optional exclusions:
  - Adhik masa
  - Kharmas: Sun in sidereal Dhanu or Meena
  - Chaturmas: Ashadha Shukla Ekadashi up to the day before Kartik Shukla Ekadashi
  - Combust Jupiter or Venus: within 11° or 10° of the Sun, 8° for retrograde Venus
- **Not applied:** lagna shuddhi, tara/chandra bala for the people involved, regional customs. The page asks readers to confirm with Acharya Ji. The rule lists should be reviewed by Acharya Ji. Childbirth timing is never suggested.

## Gochar (`Gochar`, `/gochar`, `/gochar/{planet}`, `/navagraha`)
- Current sidereal positions, sign ingresses (with retrograde re-entries), and retrograde/direct stations. Stations come from the engine's `stations` command: speed sign change, 1-day scan plus bisection, Mercury to Saturn. Spot check: Mercury turns retrograde 26 Feb 2026.
- Effects per Moon sign use the classical favourable houses counted from the natal Moon (Phaladeepika ch. 26):
  - Sun: 3, 6, 10, 11
  - Moon: 1, 3, 6, 7, 10, 11
  - Mars, Saturn, Rahu and Ketu: 3, 6, 11
  - Mercury: 2, 4, 6, 8, 10, 11
  - Jupiter: 2, 5, 7, 9, 11
  - Venus: 1–5, 8, 9, 11, 12
- Vedha is not applied. Written sign-by-sign effects are staff-authored ("Transit effects" in Admin › Rashifal) and need a second person to publish them.

## Traditional rules
- **Ashtakoota:** varna, vashya, tara, yoni, graha maitri, gana, bhakoot, nadi (36), using the North Indian tables. Parihara (cancellations) are not applied automatically.
- **Manglik:** Mars in houses 1, 2, 4, 7, 8 or 12 from the Lagna or Moon (Venus is shown for information). Cancellations are not applied.
- **Sade Sati:** Saturn in the 12th, 1st or 2nd sign from the natal Moon, including retrograde returns.
- **Pitra dosh indicators:** Sun with Rahu, Ketu or Saturn; Rahu, Ketu or Saturn in the 9th house; the 9th lord with a node. Presented as indicators, not a verdict.
- **Lal Kitab chart:** fixed houses (Aries = house 1).
- **Baby name letters:** Avakahada chakra syllables by nakshatra pada (variants exist between traditions).
- **Rudraksha:** traditional mukhi for the Lagna lord.

## Symbolic and entertainment
- **Numerology:** Chaldean letter values, Mulank (day), Bhagyank (full date), Lo Shu grid and planes, and personal year.
  Colours, days, gems and friendly or unfriendly numbers follow common Vedic-numerology tables. Labelled **symbolic**.
- **Love calculator and FLAMES:** labelled **entertainment**. They give no probability or percentage.
