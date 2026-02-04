import { Directive, EventEmitter, HostListener, Input, OnDestroy, Output } from '@angular/core';
import { Subject, debounceTime, takeUntil } from 'rxjs';

@Directive({
  selector: '[appDebounceInput]',
  standalone: true
})
export class DebounceInputDirective implements OnDestroy {
  @Input() debounceTime = 300;
  @Output() debounceInput = new EventEmitter<string>();

  private inputSubject = new Subject<string>();
  private destroy$ = new Subject<void>();

  constructor() {
    this.inputSubject.pipe(
      debounceTime(this.debounceTime),
      takeUntil(this.destroy$)
    ).subscribe(value => {
      this.debounceInput.emit(value);
    });
  }

  @HostListener('input', ['$event'])
  onInput(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.inputSubject.next(input.value);
  }

  ngOnDestroy(): void {
    this.destroy$.next();
    this.destroy$.complete();
  }
}
