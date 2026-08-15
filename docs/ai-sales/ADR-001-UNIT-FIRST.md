# ADR-001: Unit-first cold-work domain

- Статус: принято
- Дата: 2026-08-15
- Область: AI Sales / CRM domain

## Контекст

В runtime уже существуют `Unit` и legacy `Lead`. Unit объединяет contacts, locations, URI, classifications, interests, quotations, files, communications и несколько Entity. Legacy Lead используется telephony/mail intake, имеет nullable Unit/Entity links и не покрывает dossier целиком.

Создание нового AI Lead aggregate привело бы к параллельной идентичности, дублированию contacts/events/matches и неоднозначной «конвертации» в Entity.

## Решение

Durable cold-work domain строится на `App\Models\Unit` / `units`.

`Entity` остаётся точным legal/physical transaction owner. Unit не конвертируется и не удаляется после создания Entity. Отдельные новые `Lead`/`lead_*` model/table не создаются.

«Лид» — UI/аналитическое представление:

```text
Unit + UnitBusinessContext + stage + score + activity
```

## Канонический mapping

| Старое понятие | Новое каноническое понятие |
|---|---|
| Lead | Unit в sales/prospective_customer context |
| Lead Source | Unit source/evidence |
| Lead Contact | Unit contact/channel |
| Lead Good Match | Unit ↔ Good match в конкретном business context |
| Lead Score | Unit prospect score snapshot |
| Lead Event | Unit/context activity event |
| Lead Campaign Member | Unit business context membership |
| Lead conversion | human-reviewed Entity create/link, Unit остаётся |

## Legacy Lead

`App\Models\Lead` не удаляется и не переименовывается этим ADR. Он:

- остаётся источником compatibility для existing telephony/mail/dashboard;
- не получает новые AI-specific fields/relations;
- не используется как root research, campaign или scoring;
- позднее может проецироваться в Unit/context через read-only mapping и идемпотентный backfill;
- не считается доказательством правильной пары Unit↔Entity.

## Данные

Cold artifacts:

- source/observation/contact → Unit;
- match/score/draft/event/campaign membership → UnitBusinessContext;
- legal requisites/transactions → Entity.

Существующие Email/Telephone/URI/communications переиспользуются через links с provenance/context; их значения не копируются в `lead_contacts`.

## Entity boundary

AI создаёт proposal и evidence. Только authenticated human action после duplicate/requisites review может:

- создать Entity для Unit;
- attach existing Entity;
- выбрать context-specific relation.

Unit history сохраняется независимо от результата.

## Последствия

Положительные:

- одна dossier identity;
- естественная M:N Entity cardinality;
- нет transaction duplication;
- mixed-role Unit разделяется contexts;
- existing Unit UI остаётся workspace.

Стоимость:

- требуется compatibility strategy для legacy Lead;
- нужны новые context/provenance/audit capabilities;
- текущая Unit card и routes требуют authorization hardening;
- old stages/flags/pipeline надо reconciliate, а не просто удалить.

## Отклонённые альтернативы

### Новый AI Lead aggregate

Отклонён из-за параллельной identity и duplication.

### Entity-first discovery

Отклонён: web result не гарантирует real legal person, а один dossier может включать несколько Entity.

### Conversion Unit → Entity

Отклонена: уничтожает investigation history и нарушает M:N.

## Supersession

ADR имеет приоритет над любым прежним предположением о самостоятельном новом Lead-domain. Исторический commit Stage 01 не изменяется; его Unit-first выводы подтверждены.

## Контроль

Stage 03+ tests должны запрещать:

- новые AI migrations/models с `lead` как aggregate;
- AI Entity CRUD;
- cold artifact без Unit/context;
- transaction copy в Unit;
- cross-lane DTO.
