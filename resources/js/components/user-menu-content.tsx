import { Link, router } from '@inertiajs/react';
import { Check, Globe, LogOut, Settings } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuSub,
    DropdownMenuSubContent,
    DropdownMenuSubTrigger,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import {
    LOCALE_STORAGE_KEY,
    SUPPORTED_LOCALES,
    type SupportedLocale,
} from '@/lib/i18n';
import { logout } from '@/routes';
import { edit } from '@/routes/profile';
import type { User } from '@/types';

type Props = {
    user: User;
};

const LOCALE_LABELS: Record<SupportedLocale, { native: string; flag: string }> = {
    es: { native: 'Español', flag: '🇪🇸' },
    en: { native: 'English', flag: '🇬🇧' },
};

export function UserMenuContent({ user }: Props) {
    const { t, i18n } = useTranslation('nav');
    const cleanup = useMobileNavigation();

    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    const currentLocale = (i18n.language?.split('-')[0] ?? 'es') as SupportedLocale;

    const handleLanguageChange = (locale: SupportedLocale) => {
        void i18n.changeLanguage(locale);
        try {
            window.localStorage.setItem(LOCALE_STORAGE_KEY, locale);
        } catch {
            // ignore quota errors
        }
    };

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={edit()}
                        prefetch
                        onClick={cleanup}
                    >
                        <Settings className="mr-2" />
                        {t('user_menu.settings')}
                    </Link>
                </DropdownMenuItem>

                <DropdownMenuSub>
                    <DropdownMenuSubTrigger className="cursor-pointer">
                        <Globe className="mr-2 size-4" />
                        <span>
                            {t('common:language.label', {
                                ns: 'common',
                                defaultValue: 'Idioma',
                            })}
                        </span>
                        <span className="ml-auto text-xs text-muted-foreground">
                            {LOCALE_LABELS[currentLocale].flag}{' '}
                            {currentLocale.toUpperCase()}
                        </span>
                    </DropdownMenuSubTrigger>
                    <DropdownMenuSubContent className="min-w-40">
                        {SUPPORTED_LOCALES.map((locale) => {
                            const label = LOCALE_LABELS[locale];
                            const isActive = locale === currentLocale;

                            return (
                                <DropdownMenuItem
                                    key={locale}
                                    onSelect={() => handleLanguageChange(locale)}
                                    className="cursor-pointer justify-between gap-2"
                                >
                                    <span className="flex items-center gap-2">
                                        <span aria-hidden>{label.flag}</span>
                                        <span>{label.native}</span>
                                    </span>
                                    {isActive && (
                                        <Check
                                            className="size-4 text-primary"
                                            strokeWidth={2.5}
                                            aria-hidden
                                        />
                                    )}
                                </DropdownMenuItem>
                            );
                        })}
                    </DropdownMenuSubContent>
                </DropdownMenuSub>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link
                    className="block w-full cursor-pointer"
                    href={logout()}
                    as="button"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut className="mr-2" />
                    {t('user_menu.logout')}
                </Link>
            </DropdownMenuItem>
        </>
    );
}
