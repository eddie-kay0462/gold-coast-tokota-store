export function isValidEmail(value: string): boolean {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)
}

export function isValidGhanaPhone(value: string): boolean {
  return /^(\+233|0)[0-9]{9}$/.test(value)
}
