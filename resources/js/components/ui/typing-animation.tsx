import {
    useEffect,
    useMemo,
    useRef,
    useState,
    type ElementType,
} from 'react';
import { cn } from '@/lib/utils';

type TypingAnimationProps = {
    children?: string;
    words?: string[];
    className?: string;
    duration?: number;
    typeSpeed?: number;
    deleteSpeed?: number;
    delay?: number;
    pauseDelay?: number;
    loop?: boolean;
    as?: ElementType;
    startOnView?: boolean;
    showCursor?: boolean;
    blinkCursor?: boolean;
    cursorStyle?: 'line' | 'block' | 'underscore';
};

function cursorChar(style: TypingAnimationProps['cursorStyle']): string {
    if (style === 'block') {
        return '▌';
    }
    if (style === 'underscore') {
        return '_';
    }

    return '|';
}

/**
 * Escritura tipo teclado (Magic UI Typing Animation), sin dependencia de Motion.
 */
export function TypingAnimation({
    children,
    words,
    className,
    duration = 100,
    typeSpeed,
    deleteSpeed,
    delay = 0,
    pauseDelay = 1000,
    loop = false,
    as: Component = 'span',
    startOnView = false,
    showCursor = true,
    blinkCursor = true,
    cursorStyle = 'line',
}: TypingAnimationProps) {
    const wordsToAnimate = useMemo(
        () => words ?? (children ? [children] : []),
        [words, children],
    );
    const typingSpeed = typeSpeed ?? duration;
    const deletingSpeed = deleteSpeed ?? typingSpeed / 2;
    const hasMultipleWords = wordsToAnimate.length > 1;

    const elementRef = useRef<HTMLElement | null>(null);
    const [inView, setInView] = useState(!startOnView);
    const [displayedText, setDisplayedText] = useState('');
    const [wordIndex, setWordIndex] = useState(0);
    const [charIndex, setCharIndex] = useState(0);
    const [phase, setPhase] = useState<'typing' | 'pause' | 'deleting'>('typing');

    const sourceKey = useMemo(
        () => (words ? words.join('\u0000') : (children ?? '')),
        [words, children],
    );

    useEffect(() => {
        setDisplayedText('');
        setWordIndex(0);
        setCharIndex(0);
        setPhase('typing');
    }, [sourceKey]);

    useEffect(() => {
        if (!startOnView) {
            return;
        }
        const node = elementRef.current;
        if (!node) {
            return;
        }
        const observer = new IntersectionObserver(
            ([entry]) => {
                if (entry?.isIntersecting) {
                    setInView(true);
                    observer.disconnect();
                }
            },
            { threshold: 0.3 },
        );
        observer.observe(node);

        return () => observer.disconnect();
    }, [startOnView]);

    useEffect(() => {
        if (!inView || wordsToAnimate.length === 0) {
            return;
        }
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            setDisplayedText(wordsToAnimate[wordsToAnimate.length - 1] ?? '');
            setCharIndex(Array.from(wordsToAnimate[wordsToAnimate.length - 1] ?? '').length);
            setWordIndex(wordsToAnimate.length - 1);
            setPhase('typing');
            return;
        }

        const currentWord = wordsToAnimate[wordIndex] ?? '';
        const graphemes = Array.from(currentWord);
        const wait =
            delay > 0 && displayedText === '' && phase === 'typing' && charIndex === 0
                ? delay
                : phase === 'typing'
                  ? typingSpeed
                  : phase === 'deleting'
                    ? deletingSpeed
                    : pauseDelay;

        const timeout = window.setTimeout(() => {
            if (phase === 'typing') {
                if (charIndex < graphemes.length) {
                    setDisplayedText(graphemes.slice(0, charIndex + 1).join(''));
                    setCharIndex(charIndex + 1);
                    return;
                }
                if (hasMultipleWords || loop) {
                    const isLast = wordIndex === wordsToAnimate.length - 1;
                    if (!isLast || loop) {
                        setPhase('pause');
                    }
                }
                return;
            }
            if (phase === 'pause') {
                setPhase('deleting');
                return;
            }
            if (charIndex > 0) {
                setDisplayedText(graphemes.slice(0, charIndex - 1).join(''));
                setCharIndex(charIndex - 1);
                return;
            }
            setWordIndex((wordIndex + 1) % wordsToAnimate.length);
            setPhase('typing');
        }, wait);

        return () => window.clearTimeout(timeout);
    }, [
        inView,
        wordsToAnimate,
        wordIndex,
        charIndex,
        phase,
        displayedText,
        delay,
        typingSpeed,
        deletingSpeed,
        pauseDelay,
        hasMultipleWords,
        loop,
    ]);

    const currentGraphemes = Array.from(wordsToAnimate[wordIndex] ?? '');
    const isComplete =
        !loop &&
        wordIndex === wordsToAnimate.length - 1 &&
        charIndex >= currentGraphemes.length &&
        phase !== 'deleting';
    const shouldShowCursor =
        showCursor &&
        !isComplete &&
        (hasMultipleWords || loop || charIndex < currentGraphemes.length);

    return (
        <Component
            ref={elementRef}
            className="whitespace-pre-wrap"
        >
            <span className={className}>{displayedText}</span>
            {shouldShowCursor ? (
                <span
                    className={cn(
                        'ml-px inline-block font-light text-brand-600 dark:text-brand-300',
                        blinkCursor && 'animate-pulse',
                    )}
                    aria-hidden
                >
                    {cursorChar(cursorStyle)}
                </span>
            ) : null}
        </Component>
    );
}
