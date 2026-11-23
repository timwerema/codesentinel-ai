import { SeverityBadge, SourceBadge, StatusBadge } from '@/components/badges';
import { cleanup, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';

afterEach(cleanup);

describe('badges', () => {
    it('labels a scanner finding with the tool that verified it', () => {
        render(<SourceBadge source="semgrep" verified />);
        expect(screen.getByText('semgrep')).toBeTruthy();
        expect(screen.queryByText(/unverified/i)).toBeNull();
    });

    it('always marks an AI suggestion as unverified', () => {
        render(<SourceBadge source="ai" verified={false} />);
        expect(screen.getByText(/AI · unverified/)).toBeTruthy();
    });

    it('shows severity and status as text, not colour alone', () => {
        render(
            <>
                <SeverityBadge severity="critical" />
                <StatusBadge status="failed" />
                <StatusBadge status="running" />
            </>,
        );
        expect(screen.getByText('critical')).toBeTruthy();
        expect(screen.getByText('Failed')).toBeTruthy();
        expect(screen.getByText('Running')).toBeTruthy();
    });
});
