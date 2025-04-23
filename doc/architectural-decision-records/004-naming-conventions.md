# ADR 004: Convenções de Nomenclatura e Padrões de Codificação

## Status
Aceito

## Contexto
Para garantir consistência, manutenibilidade e compreensão do código em todo o projeto Adobe Commerce Enterprise Edition, é fundamental estabelecer convenções de nomenclatura claras e padrões de codificação. A ausência dessas convenções pode levar a inconsistências, dificuldades de manutenção e obstáculos na colaboração entre equipes.

Desafios atuais:
- Necessidade de consistência na nomenclatura entre diferentes módulos e componentes
- Clareza na identificação da propriedade do código e sua funcionalidade
- Alinhamento com as convenções da Adobe Commerce enquanto mantemos nossa identidade própria
- Facilitação da comunicação técnica e documentação do projeto

## Decisão
Adotaremos as seguintes convenções de nomenclatura e padrões de codificação:

1. **Namespace Vendor**:
   - Utilizaremos exclusivamente "TheDevKitchen" como nosso namespace vendor para todos os módulos personalizados
   - A estrutura de diretórios de módulos seguirá o padrão `app/code/TheDevKitchen/[NomeDoMódulo]`
   - O nome do módulo será em PascalCase, representando claramente sua funcionalidade

2. **Nomenclatura de Módulos**:
   - Módulos funcionais: `TheDevKitchen_[Funcionalidade]` (ex: TheDevKitchen_Checkout)
   - Módulos de integração: `TheDevKitchen_[SistemaExterno]` (ex: TheDevKitchen_PaymentGateway)
   - Módulos de base/core: `TheDevKitchen_Core`, `TheDevKitchen_Base`
   - Módulos de tema: `TheDevKitchen_ThemeCore`

3. **Estrutura de Temas**:
   - Os temas seguirão o padrão `app/design/frontend/TheDevKitchen/[nome-do-tema]`
   - O nome do tema será em kebab-case para maior compatibilidade com sistemas de arquivos
   - Temas administrativos seguirão `app/design/adminhtml/TheDevKitchen/[nome-do-tema]`

4. **Nomenclatura de Classes**:
   - Seguiremos rigorosamente o PSR-4 para autoloading
   - Classes seguirão PascalCase
   - Interfaces terão sufixo "Interface" (ex: `ProductRepositoryInterface`)
   - Classes abstratas terão prefixo "Abstract" (ex: `AbstractProductModel`)
   - Traits terão sufixo "Trait" (ex: `LoggableTrait`)

5. **Nomenclatura de Bancos de Dados**:
   - Tabelas: `thedevkitchen_[modulo]_[entidade]` (ex: thedevkitchen_catalog_product_custom)
   - Colunas: snake_case para nomes de colunas
   - Índices: `IDX_THEDEVKITCHEN_[ABREVIAÇÃO]_[CAMPO]`
   - Chaves estrangeiras: `FK_THEDEVKITCHEN_[TABELA]_[REFERÊNCIA]`

6. **Padrões de Codificação**:
   - Aderência aos padrões PSR-1, PSR-2, e PSR-12 para formatação de código PHP
   - Uso de declaração estrita de tipos (`declare(strict_types=1)`)
   - Documentação completa em PHPDoc para todas as classes e métodos públicos
   - Comentários em inglês para compatibilidade internacional

## Consequências
### Positivas
- Maior consistência e previsibilidade em todo o codebase
- Facilitação da identificação da origem e propósito do código
- Melhor onboarding de novos desenvolvedores
- Documentação mais clara e auto-explicativa
- Melhor integração com ferramentas automatizadas de inspeção de código

### Negativas
- Necessidade de tempo inicial para estabelecer e documentar todas as convenções
- Potencial resistência à mudança de desenvolvedores habituados a outras convenções
- Necessidade de revisões de código mais rigorosas para garantir conformidade

### Compromissos
- Criaremos e manteremos um guia de estilo de código completo como referência
- Implementaremos verificações de código automatizadas para garantir adesão aos padrões
- Revisaremos estas convenções periodicamente para assegurar que permaneçam relevantes
- Forneceremos ferramentas e templates para auxiliar desenvolvedores a seguir as convenções