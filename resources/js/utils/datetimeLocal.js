/**
 * HTML datetime-local values are wall time in the user's browser timezone (no offset in the string).
 * Laravel should receive an explicit UTC instant so display via toIso8601String() matches what was picked.
 *
 * @param {string} value Value from <input type="datetime-local" />
 * @returns {string|null} ISO 8601 UTC string, or null if invalid
 */
export function dateTimeLocalValueToUtcIso(value) {
    if (!value || typeof value !== 'string') {
        return null;
    }
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
        return null;
    }
    return parsed.toISOString();
}

/**
 * Populate datetime-local from an API UTC ISO string.
 *
 * @param {string} iso
 * @returns {string} Value suitable for datetime-local, or empty string
 */
export function utcIsoStringToDateTimeLocalValue(iso) {
    if (!iso) {
        return '';
    }
    const d = new Date(iso);
    if (Number.isNaN(d.getTime())) {
        return '';
    }
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}
