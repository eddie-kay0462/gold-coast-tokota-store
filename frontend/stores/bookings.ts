import { defineStore } from 'pinia'

export const useBookingStore = defineStore('bookings', {
  state: () => ({
    type: 'workshop' as 'workshop' | 'diy_order',
    selectedWorkshopSessionId: null as string | null,
    remainingCapacity: null as number | null,
  }),
  actions: {
    selectWorkshopSession(sessionId: string, remainingCapacity: number) {
      this.selectedWorkshopSessionId = sessionId
      this.remainingCapacity = remainingCapacity
    },
    reset() {
      this.selectedWorkshopSessionId = null
      this.remainingCapacity = null
    },
  },
})
