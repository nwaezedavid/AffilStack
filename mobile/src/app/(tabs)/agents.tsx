import { ComingSoon } from '@/components/coming-soon';

export default function AgentsScreen() {
  return (
    <ComingSoon
      title="AI agents"
      icon="sparkles-outline"
      description="Approve what your agents suggest."
      bullets={['Reply Copilot, Lead Hunter, Commission Watchdog, Content Autopilot and Offer Scout', 'Approve or reject with one tap', 'Activity feed and your weekly briefing']}
    />
  );
}
