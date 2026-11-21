import { cva, type VariantProps } from 'class-variance-authority';

export { default as Button } from './Button.vue';

// O Button do shadcn usa a mesma família do GameButton (classes .mz-* em resources/css/app.css),
// no tamanho compacto. default é o principal amarelo, destructive o perigo (os dois afundam);
// outline, secondary (suave) e ghost são calmos; link é o marca-texto.
export const buttonVariants = cva('mz-btn [&_svg]:pointer-events-none [&_svg]:size-4 [&_svg]:shrink-0', {
    variants: {
        variant: {
            default: 'mz-main',
            destructive: 'mz-danger',
            outline: 'mz-outline',
            secondary: 'mz-tonal',
            ghost: 'mz-ghost',
            link: 'mz-mark',
        },
        size: {
            default: 'mz-sm',
            sm: 'mz-xs',
            lg: 'mz-md',
            icon: 'mz-sm mz-sq',
        },
    },
    defaultVariants: {
        variant: 'default',
        size: 'default',
    },
});

export type ButtonVariants = VariantProps<typeof buttonVariants>;
