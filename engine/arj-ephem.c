/*
 * arj-ephem — small JSON command-line front end to the Swiss Ephemeris (AGPL-3.0).
 * Copyright (C) 2026 Astro Rajnath Jha platform contributors. Licensed AGPL-3.0-or-later.
 * Swiss Ephemeris (C) Astrodienst AG, used under its AGPL licence option.
 *
 * All longitudes are SIDEREAL (Lahiri / Chitrapaksha ayanamsha) unless stated.
 * Times are Julian Day numbers in UT.
 *
 * Commands (all print one JSON object on stdout; exit 0 on success, 2 on error):
 *   chart   <jd_ut> <lat> <lon> <node:mean|true>
 *   rise    <jd_ut_start> <lat> <lon>                 next sunrise/sunset/moonrise/moonset after start
 *   panchang <jd_ut_from> <jd_ut_to>                  angas at jd_from + all transitions in (from,to]
 *   cross   <planet> <target_deg> <jd_from> <jd_to>   times when sidereal longitude crosses target
 *   signs   <planet> <jd_from> <jd_to>                sign ingresses (incl. retrograde re-entries)
 */
#include <math.h>
#include <stdio.h>
#include <stdlib.h>
#include <string.h>
#include "swephexp.h"

static int g_flags = SEFLG_SWIEPH | SEFLG_SPEED | SEFLG_SIDEREAL;
static int g_used_moshier = 0;
static int g_node = SE_MEAN_NODE;

static void fail(const char *msg) {
    printf("{\"ok\":false,\"error\":\"%s\"}\n", msg);
    exit(2);
}

static double norm(double x) { x = fmod(x, 360.0); return x < 0 ? x + 360.0 : x; }

/* Sidereal longitude and speed of a body; Ketu (id -1) = Rahu + 180. */
static double lon_of(int ipl, double jd, double *speed) {
    double xx[6]; char serr[AS_MAXCH];
    int id = ipl == -1 ? g_node : ipl;
    int r = swe_calc_ut(jd, id, g_flags, xx, serr);
    if (r < 0) fail("calculation failed");
    if (!(r & SEFLG_SWIEPH)) g_used_moshier = 1;
    if (speed) *speed = xx[3];
    return ipl == -1 ? norm(xx[0] + 180.0) : xx[0];
}

/* --- Panchang angas as continuous angles (0..360) --- */
static double ang(int which, double jd) {
    double s = lon_of(SE_SUN, jd, NULL), m = lon_of(SE_MOON, jd, NULL);
    switch (which) {
        case 0: return norm(m - s);            /* tithi: 30 parts of 12 deg */
        case 1: return m;                      /* nakshatra: 27 parts */
        case 2: return norm(m + s);            /* yoga: 27 parts */
        case 3: return norm(m - s);            /* karana: 60 parts of 6 deg */
        case 4: return m;                      /* nakshatra pada: 108 parts */
    }
    return 0;
}
static const double PART[5] = {12.0, 360.0 / 27, 360.0 / 27, 6.0, 360.0 / 108};
static const char *ANGA[5] = {"tithi", "nakshatra", "yoga", "karana", "pada"};

static int idx_at(int which, double jd) { return (int)floor(ang(which, jd) / PART[which] + 1e-12); }

/* Bisection for the instant the index changes between a (index ia) and b. */
static double refine(int which, double a, double b, int ia) {
    for (int i = 0; i < 60 && (b - a) > 1e-7; i++) {
        double mid = (a + b) / 2;
        if (idx_at(which, mid) == ia) a = mid; else b = mid;
    }
    return b;
}

static void cmd_chart(int argc, char **argv) {
    if (argc < 6) fail("usage: chart jd lat lon node");
    double jd = atof(argv[2]), lat = atof(argv[3]), lon = atof(argv[4]);
    if (!isfinite(jd) || lat < -90 || lat > 90 || lon < -180 || lon > 180) fail("invalid coordinates");
    g_node = strcmp(argv[5], "true") == 0 ? SE_TRUE_NODE : SE_MEAN_NODE;
    int ids[] = {SE_SUN, SE_MOON, SE_MARS, SE_MERCURY, SE_JUPITER, SE_VENUS, SE_SATURN, SE_MEAN_NODE, -1, SE_URANUS, SE_NEPTUNE, SE_PLUTO};
    const char *names[] = {"sun", "moon", "mars", "mercury", "jupiter", "venus", "saturn", "rahu", "ketu", "uranus", "neptune", "pluto"};
    double cusps[13], ascmc[10];
    if (swe_houses_ex(jd, SEFLG_SIDEREAL, lat, lon, 'W', cusps, ascmc) < 0) fail("house calculation failed");
    printf("{\"ok\":true,\"jd_ut\":%.8f,\"ayanamsha\":%.8f,\"ascendant\":%.8f,\"mc\":%.8f,\"planets\":{", jd, swe_get_ayanamsa_ut(jd), ascmc[0], ascmc[1]);
    ids[7] = g_node; /* Rahu uses the selected node; Ketu (-1) is Rahu + 180 with the same speed */
    for (int i = 0; i < 12; i++) {
        double sp = 0;
        double l = lon_of(ids[i], jd, &sp);
        printf("%s\"%s\":{\"lon\":%.8f,\"speed\":%.8f,\"retro\":%s}", i ? "," : "", names[i], l, sp, sp < 0 ? "true" : "false");
    }
    printf("},\"ephemeris\":\"%s\",\"node\":\"%s\"}\n", g_used_moshier ? "moshier" : "swiss", g_node == SE_TRUE_NODE ? "true" : "mean");
}

static void print_event(const char *name, int ipl, int rsmi, double jd, double *geo, int *first) {
    double t; char serr[AS_MAXCH];
    int r = swe_rise_trans(jd, ipl, NULL, SEFLG_SWIEPH, rsmi, geo, 1013.25, 15, &t, serr);
    printf("%s\"%s\":", *first ? "" : ",", name);
    *first = 0;
    if (r == 0) printf("%.8f", t); else printf("null"); /* -2: body circumpolar / no event (high latitude) */
}

static void cmd_rise(int argc, char **argv) {
    if (argc < 5) fail("usage: rise jd lat lon");
    double jd = atof(argv[2]);
    double geo[3] = {atof(argv[4]), atof(argv[3]), 0};
    if (geo[1] < -90 || geo[1] > 90 || geo[0] < -180 || geo[0] > 180) fail("invalid coordinates");
    int first = 1;
    printf("{\"ok\":true,");
    print_event("sunrise", SE_SUN, SE_CALC_RISE, jd, geo, &first);
    print_event("sunset", SE_SUN, SE_CALC_SET, jd, geo, &first);
    print_event("moonrise", SE_MOON, SE_CALC_RISE, jd, geo, &first);
    print_event("moonset", SE_MOON, SE_CALC_SET, jd, geo, &first);
    printf("}\n");
}

static void cmd_panchang(int argc, char **argv) {
    if (argc < 4) fail("usage: panchang from to");
    double from = atof(argv[2]), to = atof(argv[3]);
    if (!(to > from) || to - from > 3) fail("invalid window");
    printf("{\"ok\":true,\"at\":{");
    for (int w = 0; w < 5; w++) printf("%s\"%s\":%d", w ? "," : "", ANGA[w], idx_at(w, from));
    double s = lon_of(SE_SUN, from, NULL), m = lon_of(SE_MOON, from, NULL);
    printf("},\"sun\":%.6f,\"moon\":%.6f,\"transitions\":[", s, m);
    int first = 1;
    for (int w = 0; w < 5; w++) {
        double step = 1.0 / 48; /* 30 min: shorter than any anga (shortest karana ~ 4.5 h) */
        int prev = idx_at(w, from);
        for (double t = from; t < to; t += step) {
            double b = t + step > to ? to : t + step;
            int cur = idx_at(w, b);
            if (cur != prev) {
                double at = refine(w, t, b, prev);
                printf("%s{\"anga\":\"%s\",\"from\":%d,\"to\":%d,\"jd\":%.8f}", first ? "" : ",", ANGA[w], prev, idx_at(w, at + 1e-6), at);
                first = 0;
                prev = idx_at(w, at + 1e-6);
            }
        }
    }
    printf("],\"ephemeris\":\"%s\"}\n", g_used_moshier ? "moshier" : "swiss");
}

static int planet_id(const char *n) {
    const char *names[] = {"sun", "moon", "mars", "mercury", "jupiter", "venus", "saturn", "rahu", "ketu"};
    int ids[] = {SE_SUN, SE_MOON, SE_MARS, SE_MERCURY, SE_JUPITER, SE_VENUS, SE_SATURN, SE_MEAN_NODE, -1};
    for (int i = 0; i < 9; i++) if (strcmp(n, names[i]) == 0) return ids[i];
    fail("unknown planet");
    return 0;
}

/* Generic crossing finder: f(t) = norm(lon - target) jumps from ~360 to ~0 at a crossing. */
static void crossings(int ipl, double target, double from, double to, int signs) {
    double step = ipl == SE_MOON ? 0.1 : 0.5;
    int first = 1;
    printf("{\"ok\":true,\"events\":[");
    double prevLon = lon_of(ipl, from, NULL);
    for (double t = from; t < to; t += step) {
        double b = t + step > to ? to : t + step;
        double l = lon_of(ipl, b, NULL);
        int crossed = 0; double tgt = target;
        if (signs) {
            int sa = (int)(prevLon / 30), sb = (int)(l / 30);
            if (sa != sb) { crossed = 1; /* boundary between sa and sb (direct or retrograde) */
                double d = norm(l - prevLon);
                tgt = d < 180 ? (sb * 30.0) : (sa * 30.0); }
        } else {
            double da = norm(prevLon - target), db = norm(l - target);
            if (fabs(da - db) > 180) crossed = 1;
        }
        if (crossed) {
            double a = t, c = b;
            for (int i = 0; i < 60 && c - a > 1e-7; i++) {
                double mid = (a + c) / 2, lm = lon_of(ipl, mid, NULL);
                double dA = norm(lon_of(ipl, a, NULL) - tgt), dM = norm(lm - tgt);
                if (fabs(dA - dM) > 180) c = mid; else a = mid;
            }
            double sp; double lc = lon_of(ipl, c, &sp);
            /* nudge in the direction of motion so the sign entered is reported (works for retrograde too) */
            printf("%s{\"jd\":%.8f,\"sign\":%d,\"retro\":%s}", first ? "" : ",", c, (int)(norm(lc + (sp < 0 ? -1e-5 : 1e-5)) / 30), sp < 0 ? "true" : "false");
            first = 0;
        }
        prevLon = l;
    }
    printf("],\"ephemeris\":\"%s\"}\n", g_used_moshier ? "moshier" : "swiss");
}

int main(int argc, char **argv) {
    const char *path = getenv("SE_EPHE_PATH");
    swe_set_ephe_path(path ? (char *)path : "./ephe");
    swe_set_sid_mode(SE_SIDM_LAHIRI, 0, 0);
    if (argc < 2) fail("missing command");
    if (strcmp(argv[1], "chart") == 0) cmd_chart(argc, argv);
    else if (strcmp(argv[1], "rise") == 0) cmd_rise(argc, argv);
    else if (strcmp(argv[1], "panchang") == 0) cmd_panchang(argc, argv);
    else if (strcmp(argv[1], "cross") == 0) {
        if (argc < 6) fail("usage: cross planet target from to");
        double from = atof(argv[4]), to = atof(argv[5]);
        if (!(to > from) || to - from > 40000) fail("invalid window");
        crossings(planet_id(argv[2]), norm(atof(argv[3])), from, to, 0);
    } else if (strcmp(argv[1], "signs") == 0) {
        if (argc < 5) fail("usage: signs planet from to");
        double from = atof(argv[3]), to = atof(argv[4]);
        if (!(to > from) || to - from > 40000) fail("invalid window");
        crossings(planet_id(argv[2]), 0, from, to, 1);
    } else fail("unknown command");
    swe_close();
    return 0;
}
