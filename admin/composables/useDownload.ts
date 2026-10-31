/**
 * Saves a file the admin API streams (a CSV export) without leaving the page.
 *
 * Fetched rather than linked to, so the Sanctum cookie goes with it and a
 * refusal shows as a toast instead of a page of JSON. The file name is the
 * one the API puts in Content-Disposition, else `fallbackName`.
 */
export function useDownload() {
  const config = useRuntimeConfig()
  const toast = useToast()
  const downloading = ref(false)

  async function download(path: string, fallbackName: string) {
    downloading.value = true
    try {
      const res = await $fetch.raw<Blob>(`${config.public.apiBase}${path}`, {
        credentials: 'include',
        responseType: 'blob',
        retry: 0,
      })
      const disposition = res.headers.get('content-disposition') ?? ''
      const name = disposition.match(/filename="?([^";]+)"?/)?.[1] ?? fallbackName
      const url = URL.createObjectURL(res._data!)
      const link = Object.assign(document.createElement('a'), { href: url, download: name })
      link.click()
      URL.revokeObjectURL(url)
    } catch (err) {
      const message = failureMessage(err, 'The file didn’t download. Try again in a moment.')
      if (message) toast.error('Not downloaded', message)
    } finally {
      downloading.value = false
    }
  }

  return { download, downloading }
}
