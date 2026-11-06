import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';

import { api } from '@/lib/api';
import type {
  AgentsOverview,
  BillingOverview,
  Contact,
  ContactStatus,
  Conversation,
  ConversationDetail,
  Generation,
  Offer,
  OfferDetail,
  Paginated,
  VideoStyle,
} from '@/lib/types';

const isWorking = (status?: string) => status === 'queued' || status === 'processing';

function query(params: Record<string, string | number | undefined>): string {
  const pairs = Object.entries(params).filter(([, value]) => value !== undefined && value !== '');

  return pairs.length ? `?${pairs.map(([key, value]) => `${key}=${encodeURIComponent(String(value))}`).join('&')}` : '';
}

/** Pages through a Laravel paginated list. */
function usePaged<T>(key: unknown[], path: string, params: Record<string, string | number | undefined> = {}) {
  return useInfiniteQuery({
    queryKey: [...key, params],
    initialPageParam: 1,
    queryFn: ({ pageParam }) => api<Paginated<T>>(`${path}${query({ ...params, page: pageParam })}`),
    getNextPageParam: (last) => (last.meta && last.meta.current_page < last.meta.last_page ? last.meta.current_page + 1 : undefined),
    select: (data) => data.pages.flatMap((page) => page.data),
  });
}

// --- Products and content ---------------------------------------------------

export const useOffers = (search: string) => usePaged<Offer>(['offers'], '/v1/mobile/offers', { q: search });

export function useOffer(id: number) {
  return useQuery({
    queryKey: ['offer', id],
    queryFn: () => api<OfferDetail>(`/v1/mobile/offers/${id}`),
    // Keep checking while research or content is still being written.
    refetchInterval: (q) => (q.state.data && (isWorking(q.state.data.offer.status) || q.state.data.content.some((item) => isWorking(item.status))) ? 8000 : false),
  });
}

export function useGeneration(id: number) {
  return useQuery({
    queryKey: ['generation', id],
    queryFn: async () => (await api<{ data: Generation }>(`/v1/mobile/generations/${id}`)).data,
    refetchInterval: (q) => (isWorking(q.state.data?.status) ? 6000 : false),
  });
}

export function useCreateOffer() {
  const client = useQueryClient();

  return useMutation({
    mutationFn: (input: { product_name: string; product_url: string; affiliate_network: string; affiliate_link?: string }) => api<Offer>('/v1/offers', { method: 'POST', body: input }),
    onSuccess: () => client.invalidateQueries({ queryKey: ['offers'] }),
  });
}

export function useCreateContent(offerId: number) {
  const client = useQueryClient();

  return useMutation({
    mutationFn: (input: { type: string; keyword?: string }) => api<{ data: Generation }>(`/v1/mobile/offers/${offerId}/content`, { method: 'POST', body: input }),
    onSuccess: () => {
      client.invalidateQueries({ queryKey: ['offer', offerId] });
      client.invalidateQueries({ queryKey: ['mobile', 'home'] });
    },
  });
}

// --- Videos -------------------------------------------------------------------

export function useVideoStyles() {
  return useQuery({ queryKey: ['video-styles'], queryFn: async () => (await api<{ data: VideoStyle[] }>('/v1/mobile/videos/styles')).data, staleTime: 10 * 60_000 });
}

export function useVideos() {
  const result = usePaged<Generation>(['videos'], '/v1/mobile/videos');

  return { ...result, working: (result.data ?? []).some((video) => isWorking(video.status)) };
}

export function useCreateVideo() {
  const client = useQueryClient();

  return useMutation({
    mutationFn: ({ offerId, style, photo }: { offerId: number; style: string; photo?: { uri: string; name: string; type: string } | null }) => {
      const form = new FormData();
      form.append('style', style);

      if (photo) {
        // React Native's FormData accepts { uri, name, type } for files.
        form.append('product_image', photo as unknown as Blob);
      }

      return api<{ data: Generation }>(`/v1/mobile/offers/${offerId}/videos`, { method: 'POST', body: form });
    },
    onSuccess: () => {
      client.invalidateQueries({ queryKey: ['videos'] });
      client.invalidateQueries({ queryKey: ['mobile', 'home'] });
    },
  });
}

// --- Agents ---------------------------------------------------------------------

export function useAgents() {
  return useQuery({ queryKey: ['agents'], queryFn: () => api<AgentsOverview>('/v1/mobile/agents') });
}

export function useAgentDecision() {
  const client = useQueryClient();

  return useMutation({
    mutationFn: ({ id, decision }: { id: number; decision: 'approve' | 'dismiss' }) => api<{ message: string }>(`/v1/mobile/agents/activities/${id}/${decision}`, { method: 'POST' }),
    onSettled: () => {
      client.invalidateQueries({ queryKey: ['agents'] });
      client.invalidateQueries({ queryKey: ['mobile', 'home'] });
    },
  });
}

// --- Inbox ----------------------------------------------------------------------

export function useInbox() {
  return useQuery({ queryKey: ['inbox'], queryFn: () => api<{ data: Conversation[]; email_connected: boolean }>('/v1/mobile/inbox') });
}

export function useConversation(contactId: number) {
  return useQuery({ queryKey: ['conversation', contactId], queryFn: () => api<ConversationDetail>(`/v1/mobile/inbox/${contactId}`) });
}

export function useReplyActions(contactId: number) {
  const client = useQueryClient();
  const refresh = () => {
    client.invalidateQueries({ queryKey: ['conversation', contactId] });
    client.invalidateQueries({ queryKey: ['inbox'] });
  };

  return {
    draft: useMutation({ mutationFn: ({ replyId, instructions }: { replyId: number; instructions?: string }) => api<{ body: string }>(`/v1/mobile/inbox/replies/${replyId}/draft`, { method: 'POST', body: { instructions } }) }),
    send: useMutation({
      mutationFn: ({ replyId, subject, body }: { replyId: number; subject: string; body: string }) => api<{ message: string }>(`/v1/mobile/inbox/replies/${replyId}/send`, { method: 'POST', body: { subject, body } }),
      onSuccess: refresh,
    }),
    dismiss: useMutation({ mutationFn: (replyId: number) => api(`/v1/mobile/inbox/replies/${replyId}/suggestion/dismiss`, { method: 'POST' }), onSuccess: refresh }),
  };
}

// --- Contacts -------------------------------------------------------------------

export const useContacts = (search: string, status?: ContactStatus) => usePaged<Contact>(['contacts'], '/v1/mobile/contacts', { q: search, status });

export function useContact(id: number) {
  return useQuery({ queryKey: ['contact', id], queryFn: async () => (await api<{ data: Contact }>(`/v1/mobile/contacts/${id}`)).data });
}

export function useUpdateContact(id: number) {
  const client = useQueryClient();

  return useMutation({
    mutationFn: (input: Partial<Pick<Contact, 'status' | 'notes' | 'tags'>>) => api<{ data: Contact }>(`/v1/mobile/contacts/${id}`, { method: 'PATCH', body: input }),
    onSuccess: (result) => {
      client.setQueryData(['contact', id], result.data);
      client.invalidateQueries({ queryKey: ['contacts'] });
    },
  });
}

// --- Plan & credits (view only) ------------------------------------------------

export function useBilling() {
  return useQuery({ queryKey: ['billing'], queryFn: () => api<{ data: BillingOverview }>('/v1/mobile/billing'), select: (response) => response.data });
}
