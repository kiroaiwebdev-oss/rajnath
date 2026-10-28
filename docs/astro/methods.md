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
- **Not yet implemented:**
  - amanta/purnimanta month names and adhik masa
  - Choghadiya and Hora
  - festival rules
  - event-specific Muhurat search

  These need versioned regional rules and expert review before publication.

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
