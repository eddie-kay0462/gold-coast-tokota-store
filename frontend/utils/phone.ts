import { countryName } from '~/utils/geo'

/**
 * Phone numbers as two parts, a country's calling code and the national
 * number, joined into E.164 (`+233241234567`) for the API.
 *
 * Calling codes by ISO 3166-1 alpha-2. `Intl` has country names but not
 * calling codes, so they live here. Same codes as `utils/countries.ts`.
 */
const CALLING_CODES: Record<string, string> = Object.fromEntries(
  (
    'AD376 AE971 AF93 AG1268 AI1264 AL355 AM374 AO244 AR54 AS1684 AT43 AU61 AW297 AX358 AZ994 '
    + 'BA387 BB1246 BD880 BE32 BF226 BG359 BH973 BI257 BJ229 BL590 BM1441 BN673 BO591 BQ599 BR55 BS1242 '
    + 'BT975 BW267 BY375 BZ501 CA1 CC61 CD243 CF236 CG242 CH41 CI225 CK682 CL56 CM237 CN86 '
    + 'CO57 CR506 CU53 CV238 CW599 CX61 CY357 CZ420 DE49 DJ253 DK45 DM1767 DO1809 DZ213 EC593 EE372 '
    + 'EG20 EH212 ER291 ES34 ET251 FI358 FJ679 FK500 FM691 FO298 FR33 GA241 GB44 GD1473 GE995 GF594 '
    + 'GG44 GH233 GI350 GL299 GM220 GN224 GP590 GQ240 GR30 GT502 GU1671 GW245 GY592 HK852 '
    + 'HN504 HR385 HT509 HU36 ID62 IE353 IL972 IM44 IN91 IO246 IQ964 IR98 IS354 IT39 JE44 JM1876 '
    + 'JO962 JP81 KE254 KG996 KH855 KI686 KM269 KN1869 KP850 KR82 KW965 KY1345 KZ7 LA856 LB961 LC1758 '
    + 'LI423 LK94 LR231 LS266 LT370 LU352 LV371 LY218 MA212 MC377 MD373 ME382 MF590 MG261 MH692 MK389 '
    + 'ML223 MM95 MN976 MO853 MP1670 MQ596 MR222 MS1664 MT356 MU230 MV960 MW265 MX52 MY60 MZ258 NA264 '
    + 'NC687 NE227 NF672 NG234 NI505 NL31 NO47 NP977 NR674 NU683 NZ64 OM968 PA507 PE51 PF689 PG675 '
    + 'PH63 PK92 PL48 PM508 PR1787 PS970 PT351 PW680 PY595 QA974 RE262 RO40 RS381 RU7 RW250 '
    + 'SA966 SB677 SC248 SD249 SE46 SG65 SH290 SI386 SJ47 SK421 SL232 SM378 SN221 SO252 SR597 SS211 '
    + 'ST239 SV503 SX1721 SY963 SZ268 TC1649 TD235 TG228 TH66 TJ992 TK690 TL670 TM993 TN216 TO676 '
    + 'TR90 TT1868 TV688 TW886 TZ255 UA380 UG256 US1 UY598 UZ998 VA39 VC1784 VE58 VG1284 VI1340 '
    + 'VN84 VU678 WF681 WS685 YE967 YT262 ZA27 ZM260 ZW263'
  ).split(' ').map((entry) => [entry.slice(0, 2), entry.slice(2)]),
)

/**
 * Countries where a number is written at home with a leading 0 (the trunk
 * prefix) that is dropped after the calling code: 024 123 4567 in Ghana is
 * +233 24 123 4567. Not every country: in Italy or Côte d'Ivoire the 0 is
 * part of the number, so it is only stripped for these.
 */
const TRUNK_ZERO = new Set([
  'GH', 'NG', 'KE', 'ZA', 'UG', 'TZ', 'RW', 'ZM', 'ZW', 'MW', 'ET', 'EG', 'MA', 'DZ', 'TN', 'SL',
  'LR', 'NA', 'SD', 'SS', 'GB', 'GG', 'JE', 'IM', 'IE', 'DE', 'FR', 'NL', 'BE', 'AT', 'CH', 'SE',
  'FI', 'TR', 'IL', 'AE', 'SA', 'IN', 'PK', 'BD', 'LK', 'JP', 'KR', 'CN', 'TW', 'TH', 'MY', 'ID',
  'PH', 'VN', 'AU', 'NZ',
])

/** National-number lengths where they're fixed and worth checking exactly. */
const EXACT_LENGTH: Record<string, number> = { GH: 9 }

export const DEFAULT_PHONE_COUNTRY = 'GH'

export function callingCode(country: string): string {
  return CALLING_CODES[country] ?? ''
}

export function dropsLeadingZero(country: string): boolean {
  return TRUNK_ZERO.has(country)
}

/** Ghana first, then everyone else by name. Labels read "Ghana (+233)". */
export function callingCodeOptions(locale = 'en'): { value: string, label: string }[] {
  const label = (code: string) => `${countryName(code, locale)} (+${CALLING_CODES[code]})`
  const others = Object.keys(CALLING_CODES)
    .filter((code) => code !== DEFAULT_PHONE_COUNTRY)
    .map((code) => ({ value: code, label: label(code) }))
    .sort((a, b) => a.label.localeCompare(b.label, locale))
  return [{ value: DEFAULT_PHONE_COUNTRY, label: label(DEFAULT_PHONE_COUNTRY) }, ...others]
}

/** What the number box suggests, so people know to leave the 0 off. */
export function nationalPlaceholder(country: string): string {
  if (country === 'GH') return 'e.g. 241234567 (no 0 at the start)'
  return dropsLeadingZero(country) ? 'Number without the 0 at the start' : 'Phone number'
}

/**
 * The national number as typed, cleaned: spaces, dashes and brackets go, a
 * pasted "+233" (or "00233") for the chosen country goes, and the trunk 0
 * goes where the country drops it.
 */
export function normaliseNational(country: string, value: string): string {
  const code = callingCode(country)
  let digits = value.trim().replace(/^\+/, '00').replace(/\D/g, '')
  if (code && digits.startsWith(`00${code}`)) digits = digits.slice(2 + code.length)
  if (dropsLeadingZero(country)) digits = digits.replace(/^0+/, '')
  return digits
}

/** An error message, or undefined when the number is acceptable. */
export function nationalNumberError(country: string, value: string): string | undefined {
  const digits = normaliseNational(country, value)
  if (!callingCode(country)) return 'Choose your country code.'
  if (/[^\d\s\-()+.]/.test(value)) return 'Use digits only.'
  const exact = EXACT_LENGTH[country]
  if (exact && digits.length !== exact) {
    return `A Ghana number has ${exact} digits after +233, e.g. 241234567.`
  }
  // E.164 allows 15 digits in all, calling code included.
  if (digits.length < 6 || digits.length + callingCode(country).length > 15) {
    return 'That doesn’t look like a valid phone number.'
  }
  return undefined
}

/** `+233241234567`, or null for an empty number. */
export function toE164(country: string, value: string): string | null {
  const digits = normaliseNational(country, value)
  return digits ? `+${callingCode(country)}${digits}` : null
}
