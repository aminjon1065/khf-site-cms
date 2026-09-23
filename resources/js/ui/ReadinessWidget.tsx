import { AlertCircle, Check, CheckCircle2, Sparkles } from 'lucide-react';
import React from 'react';
import { cn } from '@/lib/utils';

export interface ReadinessItem {
    id?: string;
    label: string;
    done: boolean;
    hint?: string;
}

export interface ReadinessWidgetProps {
    title?: string;
    score: number;
    statusText?: string;
    items: ReadinessItem[];
    className?: string;
}

/**
 * Виджет оценки готовности материала (WordPress-like Traffic Light / Quality Score).
 * Отображает визуальный счётчик готовности, цветной прогресс-бар, статус публикации
 * и список обязательных/рекомендуемых полей с чек-иконками.
 */
export function ReadinessWidget({
    title = 'Оценка готовности',
    score,
    statusText,
    items,
    className,
}: ReadinessWidgetProps) {
    const clampedScore = Math.max(0, Math.min(100, Math.round(score)));
    const tier =
        clampedScore >= 80 ? 'high' : clampedScore >= 50 ? 'mid' : 'low';

    const defaultStatus =
        clampedScore >= 80
            ? 'Готово к согласованию и публикации'
            : clampedScore >= 50
              ? 'Черновик сформирован, дополните детали'
              : 'Не все ключевые поля заполнены';

    const displayStatus = statusText ?? defaultStatus;

    return (
        <div className={cn('wp-readiness-widget', className)}>
            {/* Заголовок и бейдж с процентом */}
            <div className="wp-readiness-header">
                <div className="wp-readiness-title">
                    <Sparkles size={15} className="wp-readiness-title-icon" />
                    <span>{title}</span>
                </div>
                <div className={cn('wp-readiness-score-tag', `is-${tier}`)}>
                    {clampedScore}%
                </div>
            </div>

            {/* Прогресс-бар со шкалой и анимацией заполнения */}
            <div className="wp-readiness-bar">
                <div
                    className={cn('wp-readiness-fill', `is-${tier}`)}
                    style={{ width: `${Math.max(clampedScore, 4)}%` }}
                />
            </div>

            {/* Баннер статуса готовности */}
            <div className={cn('wp-readiness-status', `is-${tier}`)}>
                <span className="wp-readiness-status-icon">
                    {tier === 'high' ? (
                        <CheckCircle2 size={14} strokeWidth={2.2} />
                    ) : (
                        <AlertCircle size={14} strokeWidth={2.2} />
                    )}
                </span>
                <span className="wp-readiness-status-text">
                    {displayStatus}
                </span>
            </div>

            {/* Контрольный список обязательных и рекомендуемых пунктов */}
            <ul className="wp-readiness-checklist">
                {items.map((item, idx) => (
                    <li
                        key={item.id ?? idx}
                        className={cn(
                            'wp-readiness-check-item',
                            item.done ? 'is-done' : 'is-pending',
                        )}
                    >
                        <span
                            className="wp-readiness-check-icon"
                            aria-hidden="true"
                        >
                            {item.done ? (
                                <Check size={11} strokeWidth={3} />
                            ) : (
                                <span className="wp-readiness-check-dot" />
                            )}
                        </span>
                        <span className="wp-readiness-check-label">
                            {item.label}
                        </span>
                        {item.hint && (
                            <span className="wp-readiness-check-hint">
                                {item.hint}
                            </span>
                        )}
                    </li>
                ))}
            </ul>
        </div>
    );
}
