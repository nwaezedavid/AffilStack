import { ComingSoon } from '@/components/coming-soon';

export default function ProductsScreen() {
  return (
    <ComingSoon
      title="Products"
      icon="cube-outline"
      description="Your products, research and AI content."
      bullets={['Add a product from its link', 'See the research and buyer insights', 'Generate articles, posts, emails and scripts, then copy or share']}
    />
  );
}
